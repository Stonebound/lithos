<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Release;
use Illuminate\Support\Facades\Storage;

class ModpackImporter
{
    public function import(Release $release): string
    {
        $baseDir = 'modpacks/'.$release->id;
        $targetDir = $baseDir.'/new';
        Storage::disk('local')->makeDirectory($targetDir);

        if ($release->source_type === 'zip') {
            $zip = new \ZipArchive;
            if ($zip->open($release->source_path) !== true) {
                throw new \RuntimeException('Failed to open zip: '.$release->source_path);
            }
            // extract to local dir, then move contents to targetDir
            $zip->extractTo(Storage::disk('local')->path($targetDir));
            $zip->close();
        } else {
            $sourceDir = $this->normalizeLocalPath($release->source_path);
            $this->copyDirectory($sourceDir, $targetDir);
        }

        return $targetDir;
    }

    private function copyDirectory(string $sourceDir, string $targetDir): void
    {
        $disk = Storage::disk('local');

        $disk->makeDirectory($targetDir);

        foreach ($disk->allFiles($sourceDir) as $sourceFile) {
            $relative = ltrim(str_replace($sourceDir.'/', '', $sourceFile), '/');
            $targetFile = $targetDir.'/'.$relative;
            $targetParent = dirname($targetFile);
            if ($targetParent !== '.' && ! $disk->exists($targetParent)) {
                $disk->makeDirectory($targetParent);
            }

            $disk->put($targetFile, $disk->get($sourceFile));
        }
    }

    private function normalizeLocalPath(string $path): string
    {
        $disk = Storage::disk('local');
        $root = rtrim($disk->path(''), '/');
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, $root.'/')) {
            return ltrim(substr($normalized, strlen($root)), '/');
        }

        return ltrim($normalized, '/');
    }
}
