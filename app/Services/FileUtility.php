<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class FileUtility
{
    /**
     * Recursively delete a directory and all its contents from the local storage disk.
     */
    public static function deleteDirectory(string $dir): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($dir)) {
            $disk->deleteDirectory($dir);
        }
    }

    /**
     * Determine if a file is binary.
     */
    public static function isBinary(string $path): bool
    {
        $disk = Storage::disk('local');

        if ($path === '' || ! $disk->exists($path)) {
            return false;
        }

        $fullPath = $disk->path($path);
        $handle = fopen($fullPath, 'rb');
        if (! $handle) {
            return false;
        }

        $contents = fread($handle, 1024);
        fclose($handle);

        if ($contents === false) {
            return true;
        }

        return str_contains($contents, "\0");
    }

    /**
     * Determine if a file has a known binary extension.
     */
    public static function hasBinaryExtension(string $relative): bool
    {
        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        return in_array($ext, ['jar', 'zip', 'png', 'jpg', 'jpeg', 'gif', 'bin', 'exe', 'dll', 'so']);
    }

    public static function hasSufficientDiskspace(): bool
    {
        // local disk should have more than 10gb free
        $disk = Storage::disk('local');
        $path = $disk->path('/');
        $freeBytes = disk_free_space($path);

        return $freeBytes !== false && $freeBytes > 10 * 1024 * 1024 * 1024;
    }
}
