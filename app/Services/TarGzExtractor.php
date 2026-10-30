<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Streaming extractor for .tar.gz archives produced by Pterodactyl Wings.
 *
 * Only regular files are written. Symlinks, hardlinks, devices and FIFOs are
 * ignored, and any entry whose path is absolute or contains ".." aborts the
 * extraction, so nothing can ever be written outside the target directory.
 */
class TarGzExtractor
{
    private const int BLOCK_SIZE = 512;

    private const int COPY_CHUNK_SIZE = 1048576;

    /**
     * Extract regular files from $archivePath into $targetDir (absolute path).
     *
     * @param  (callable(string): bool)|null  $accept  Receives the normalized relative path; return false to skip the file.
     * @return int Number of files written.
     *
     * @throws \RuntimeException
     */
    public function extract(string $archivePath, string $targetDir, ?callable $accept = null): int
    {
        $archive = gzopen($archivePath, 'rb');
        if ($archive === false) {
            throw new \RuntimeException('Unable to open archive: '.$archivePath);
        }

        $targetDir = rtrim($targetDir, '/');
        $written = 0;
        $longName = null;
        $paxPath = null;
        $paxSize = null;

        try {
            while (true) {
                $header = $this->readBytes($archive, self::BLOCK_SIZE, allowEof: true);
                if ($header === null || trim($header, "\0") === '') {
                    break;
                }

                $this->verifyChecksum($header);

                $type = $header[156];
                $size = $paxSize ?? $this->parseNumber(substr($header, 124, 12));
                $path = $paxPath ?? $longName ?? $this->headerPath($header);

                if ($type === 'x' || $type === 'L') {
                    $data = $this->readEntryData($archive, $size);
                    if ($type === 'x') {
                        [$paxPath, $paxSize] = $this->parsePaxHeaders($data);
                    } else {
                        $longName = rtrim($data, "\0");
                    }

                    continue;
                }

                $longName = null;
                $paxPath = null;
                $paxSize = null;

                if ($type === 'g') {
                    $this->skipEntryData($archive, $size);

                    continue;
                }

                if ($type !== '0' && $type !== "\0" && $type !== '7') {
                    $this->skipEntryData($archive, $size);

                    continue;
                }

                $relative = $this->normalizePath($path);

                if ($accept !== null && ! $accept($relative)) {
                    $this->skipEntryData($archive, $size);

                    continue;
                }

                $this->writeEntry($archive, $targetDir.'/'.$relative, $size);
                $written++;
            }
        } finally {
            gzclose($archive);
        }

        return $written;
    }

    /**
     * @param  resource  $archive
     */
    private function writeEntry($archive, string $destination, int $size): void
    {
        $directory = dirname($destination);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to create directory: '.$directory);
        }

        $handle = fopen($destination, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to write file: '.$destination);
        }

        try {
            $remaining = $size;
            while ($remaining > 0) {
                $chunk = $this->readBytes($archive, min(self::COPY_CHUNK_SIZE, $remaining));
                if (fwrite($handle, $chunk) !== strlen($chunk)) {
                    throw new \RuntimeException('Unable to write file: '.$destination);
                }
                $remaining -= strlen($chunk);
            }
        } finally {
            fclose($handle);
        }

        $this->skipPadding($archive, $size);
    }

    /**
     * @param  resource  $archive
     */
    private function readEntryData($archive, int $size): string
    {
        $data = $size > 0 ? $this->readBytes($archive, $size) : '';
        $this->skipPadding($archive, $size);

        return $data;
    }

    /**
     * @param  resource  $archive
     */
    private function skipEntryData($archive, int $size): void
    {
        $remaining = $size;
        while ($remaining > 0) {
            $remaining -= strlen($this->readBytes($archive, min(self::COPY_CHUNK_SIZE, $remaining)));
        }

        $this->skipPadding($archive, $size);
    }

    /**
     * @param  resource  $archive
     */
    private function skipPadding($archive, int $size): void
    {
        $padding = (self::BLOCK_SIZE - ($size % self::BLOCK_SIZE)) % self::BLOCK_SIZE;
        if ($padding > 0) {
            $this->readBytes($archive, $padding);
        }
    }

    /**
     * @param  resource  $archive
     * @return ($allowEof is true ? string|null : string)
     */
    private function readBytes($archive, int $length, bool $allowEof = false): ?string
    {
        $buffer = '';
        while (strlen($buffer) < $length) {
            $chunk = gzread($archive, $length - strlen($buffer));
            if ($chunk === false || $chunk === '') {
                if ($allowEof && $buffer === '') {
                    return null;
                }

                throw new \RuntimeException('Unexpected end of archive.');
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function verifyChecksum(string $header): void
    {
        $expected = $this->parseNumber(substr($header, 148, 8));
        $actual = array_sum(array_map(ord(...), str_split(substr($header, 0, 148).str_repeat(' ', 8).substr($header, 156))));

        if ($expected !== $actual) {
            throw new \RuntimeException('Corrupt archive: header checksum mismatch.');
        }
    }

    private function headerPath(string $header): string
    {
        $name = rtrim(substr($header, 0, 100), "\0");
        $prefix = substr($header, 257, 5) === 'ustar' ? rtrim(substr($header, 345, 155), "\0") : '';

        return $prefix !== '' ? $prefix.'/'.$name : $name;
    }

    /**
     * Parse an octal (or GNU base-256) numeric header field.
     */
    private function parseNumber(string $field): int
    {
        if ($field !== '' && (ord($field[0]) & 0x80) !== 0) {
            $value = ord($field[0]) & 0x7F;
            for ($i = 1, $length = strlen($field); $i < $length; $i++) {
                $value = ($value << 8) | ord($field[$i]);
            }

            return $value;
        }

        $octal = trim($field, " \0");
        if ($octal === '') {
            return 0;
        }

        if (preg_match('/^[0-7]+$/', $octal) !== 1) {
            throw new \RuntimeException('Corrupt archive: invalid numeric field.');
        }

        return (int) octdec($octal);
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private function parsePaxHeaders(string $data): array
    {
        $path = null;
        $size = null;
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $space = strpos($data, ' ', $offset);
            if ($space === false) {
                break;
            }

            $recordLength = (int) substr($data, $offset, $space - $offset);
            if ($recordLength <= 0) {
                break;
            }

            $record = substr($data, $space + 1, $recordLength - ($space - $offset) - 2);
            $offset += $recordLength;

            [$key, $value] = array_pad(explode('=', $record, 2), 2, '');
            if ($key === 'path') {
                $path = $value;
            } elseif ($key === 'size' && preg_match('/^\d+$/', $value) === 1) {
                $size = (int) $value;
            }
        }

        return [$path, $size];
    }

    /**
     * @throws \RuntimeException
     */
    private function normalizePath(string $path): string
    {
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/')) {
            throw new \RuntimeException('Unsafe path in archive: '.$path);
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \RuntimeException('Unsafe path in archive: '.$path);
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new \RuntimeException('Unsafe path in archive: '.$path);
        }

        return implode('/', $segments);
    }
}
