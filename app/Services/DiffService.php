<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FileChangeType;
use App\Models\FileChange;
use App\Models\Release;
use Illuminate\Support\Facades\Storage;

class DiffService
{
    public function compute(Release $release, string $oldDir, string $newDir): array
    {
        $oldFiles = $this->mapFiles($oldDir);
        $newFiles = $this->mapFiles($newDir);

        $skipPatterns = \App\Models\OverrideRule::getSkipPatternsForServer($release->server);

        $changes = [];

        // Added or Modified
        foreach ($newFiles as $relative => $newPath) {
            if ($this->shouldSkip($relative, $skipPatterns)) {
                continue;
            }

            $oldPath = $oldFiles[$relative] ?? null;
            if (! $oldPath) {
                $changes[] = $this->buildChange($release, $relative, FileChangeType::Added, null, $newPath);
            } else {
                if (! $this->sameFile($oldPath, $newPath)) {
                    $changes[] = $this->buildChange($release, $relative, FileChangeType::Modified, $oldPath, $newPath);
                }
            }
        }

        // Removed
        foreach ($oldFiles as $relative => $oldPath) {
            if ($this->shouldSkip($relative, $skipPatterns)) {
                continue;
            }

            if (! isset($newFiles[$relative])) {
                $changes[] = $this->buildChange($release, $relative, FileChangeType::Removed, $oldPath, null);
            }
        }

        return $changes;
    }

    protected function shouldSkip(string $path, array $skipPatterns): bool
    {
        foreach ($skipPatterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    protected function mapFiles(string $dir): array
    {
        $disk = Storage::disk('local');

        $result = [];
        foreach ($disk->allFiles($dir) as $path) {
            $relative = ltrim(str_replace($dir.'/', '', $path), '/');
            $result[$relative] = $path;
        }

        return $result;
    }

    protected function sameFile(string $pathA, string $pathB): bool
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($pathA) || ! $disk->exists($pathB)) {
            return false;
        }

        $sizeA = $disk->size($pathA);
        $sizeB = $disk->size($pathB);
        if ($sizeA !== $sizeB) {
            return false;
        }

        $hashA = hash_file('sha256', $disk->path($pathA));
        $hashB = hash_file('sha256', $disk->path($pathB));

        return $hashA === $hashB;
    }

    protected function buildChange(Release $release, string $relative, FileChangeType $type, ?string $oldPath, ?string $newPath): FileChange
    {
        $disk = Storage::disk('local');
        $isBinary = $this->isBinary($oldPath ?? $newPath ?? '') || $this->hasBinaryExtension($relative);
        $diffSummary = null;

        $sizeOld = $oldPath && $disk->exists($oldPath) ? $disk->size($oldPath) : null;
        $sizeNew = $newPath && $disk->exists($newPath) ? $disk->size($newPath) : null;

        $checksumOld = null;
        if ($oldPath && $disk->exists($oldPath)) {
            $checksumOld = hash_file('sha256', $disk->path($oldPath));
        }

        $checksumNew = null;
        if ($newPath && $disk->exists($newPath)) {
            $checksumNew = hash_file('sha256', $disk->path($newPath));
        }

        if ($type === FileChangeType::Modified && ! $isBinary && $oldPath && $newPath) {
            // Only generate diff for files under 1MB to avoid OOM
            if (($sizeOld ?? 0) < 1024 * 1024 && ($sizeNew ?? 0) < 1024 * 1024) {
                $diffSummary = $this->generateDiffSummary($oldPath, $newPath);
            } else {
                $diffSummary = 'File too large for diff summary.';
            }
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
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return false;
        }

        $fullPath = Storage::disk('local')->path($path);
        $handle = fopen($fullPath, 'rb');
        if (! $handle) {
            return false;
        }

        $contents = fread($handle, 1024);
        fclose($handle);

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
        $disk = Storage::disk('local');
        $a = explode("\n", rtrim($disk->get($oldPath), "\n"));
        $b = explode("\n", rtrim($disk->get($newPath), "\n"));
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
