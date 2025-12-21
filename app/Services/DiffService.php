<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FileChange;
use App\Models\Release;

class DiffService
{
    public function compute(Release $release, string $oldDir, string $newDir): array
    {
        $oldFiles = $this->mapFiles($oldDir);
        $newFiles = $this->mapFiles($newDir);

        $changes = [];

        // Added or Modified
        foreach ($newFiles as $relative => $newPath) {
            $oldPath = $oldFiles[$relative] ?? null;
            if (! $oldPath) {
                $changes[] = $this->buildChange($release, $relative, 'added', null, $newPath);
            } else {
                if (! $this->sameFile($oldPath, $newPath)) {
                    $changes[] = $this->buildChange($release, $relative, 'modified', $oldPath, $newPath);
                }
            }
        }

        // Removed
        foreach ($oldFiles as $relative => $oldPath) {
            if (! isset($newFiles[$relative])) {
                $changes[] = $this->buildChange($release, $relative, 'removed', $oldPath, null);
            }
        }

        return $changes;
    }

    protected function mapFiles(string $dir): array
    {
        $result = [];
        if (! is_dir($dir)) {
            return $result;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $relative = str_replace($dir.'/', '', $file->getPathname());
            $result[$relative] = $file->getPathname();
        }

        return $result;
    }

    protected function sameFile(string $pathA, string $pathB): bool
    {
        if (filesize($pathA) !== filesize($pathB)) {
            return false;
        }

        return hash_file('sha256', $pathA) === hash_file('sha256', $pathB);
    }

    protected function buildChange(Release $release, string $relative, string $type, ?string $oldPath, ?string $newPath): FileChange
    {
        $isBinary = $this->isBinary($oldPath ?? $newPath ?? '') || $this->hasBinaryExtension($relative);
        $diffSummary = null;
        $sizeOld = $oldPath ? filesize($oldPath) : null;
        $sizeNew = $newPath ? filesize($newPath) : null;
        $checksumOld = $oldPath ? hash_file('sha256', $oldPath) : null;
        $checksumNew = $newPath ? hash_file('sha256', $newPath) : null;

        if ($type === 'modified' && ! $isBinary && $oldPath && $newPath) {
            $diffSummary = $this->generateDiffSummary($oldPath, $newPath);
        }

        return new FileChange([
            'release_id' => $release->id,
            'relative_path' => $relative,
            'change_type' => $type,
            'is_binary' => $isBinary,
            'diff_summary' => $diffSummary,
            'checksum_old' => $checksumOld,
            'checksum_new' => $checksumNew,
            'size_old' => $sizeOld,
            'size_new' => $sizeNew,
        ]);
    }

    protected function isBinary(string $path): bool
    {
        if ($path === '' || ! is_file($path)) {
            return false;
        }
        $contents = file_get_contents($path, false, null, 0, 1024);
        if ($contents === false) {
            return true;
        }

        // Heuristic: if there are null bytes, treat as binary
        return strpos($contents, "\0") !== false;
    }

    protected function hasBinaryExtension(string $relative): bool
    {
        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        return in_array($ext, ['jar', 'zip', 'png', 'jpg', 'jpeg', 'gif', 'bin']);
    }

    protected function generateDiffSummary(string $oldPath, string $newPath): string
    {
        $a = @file($oldPath, FILE_IGNORE_NEW_LINES);
        $b = @file($newPath, FILE_IGNORE_NEW_LINES);
        $diff = [];
        $max = max(count($a ?? []), count($b ?? []));
        for ($i = 0; $i < $max; $i++) {
            $lineA = $a[$i] ?? null;
            $lineB = $b[$i] ?? null;
            if ($lineA !== $lineB) {
                $diff[] = sprintf('-%s', $lineA ?? '');
                $diff[] = sprintf('+%s', $lineB ?? '');
            }
            if (count($diff) > 200) {
                $diff[] = '...';
                break;
            }
        }

        return implode("\n", $diff);
    }
}
