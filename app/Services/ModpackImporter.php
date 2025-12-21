<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Release;

class ModpackImporter
{
    public function import(Release $release): string
    {
        $baseDir = storage_path('app/modpacks/'.$release->id);
        $targetDir = $baseDir.'/new';
        $this->ensureDir($targetDir);

        if ($release->source_type === 'zip') {
            $zip = new \ZipArchive;
            if ($zip->open($release->source_path) !== true) {
                throw new \RuntimeException('Failed to open zip: '.$release->source_path);
            }
            $zip->extractTo($targetDir);
            $zip->close();
        } else {
            $this->copyDirectory($release->source_path, $targetDir);
        }

        return $targetDir;
    }

    protected function copyDirectory(string $source, string $dest): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $target = $dest.'/'.str_replace($source.'/', '', $item->getPathname());
            if ($item->isDir()) {
                $this->ensureDir($target);
            } else {
                $this->ensureDir(dirname($target));
                copy($item->getPathname(), $target);
            }
        }
    }

    protected function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}
