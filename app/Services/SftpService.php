<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Facades\Storage;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;

class SftpService
{
    public function connect(Server $server): SFTP
    {
        $sftp = new SFTP($server->host, $server->port);

        if ($server->auth_type === 'password') {
            if (! $sftp->login($server->username, $server->password ?? '')) {
                throw new \RuntimeException('SFTP password login failed');
            }
        } else {
            $keyContents = null;
            $keyContents = Storage::disk('local')->get($server->private_key_path);
            $key = PublicKeyLoader::load($keyContents);
            if (! $sftp->login($server->username, $key)) {
                throw new \RuntimeException('SFTP key login failed');
            }
        }

        return $sftp;
    }

    public function downloadDirectory(SFTP $sftp, string $remotePath, string $localPath, array $includeTopDirs = [], int $depth = 0): void
    {
        $items = $sftp->rawlist($remotePath, true);
        if (! is_array($items)) {
            throw new \RuntimeException('Failed to list remote path: '.$remotePath);
        }

        if (empty($includeTopDirs)) {
            $includeTopDirs = ['config', 'mods', 'kubejs', 'defaultconfigs', 'resourcepacks'];
        }

        foreach ($items as $name => $meta) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $remoteItem = rtrim($remotePath, '/').'/'.$name;
            $relative = $name;
            $isDir = $sftp->is_dir($remoteItem);
            if ($depth === 0 && ! empty($includeTopDirs) && $isDir && ! in_array($name, $includeTopDirs, true)) {
                continue;
            }

            $isLink = $sftp->is_link($remoteItem);
            if ($isLink) {
                continue; // Skip symlinks
            }

            if ($isDir) {
                $this->downloadDirectory($sftp, $remoteItem, $localPath.'/'.$relative, $includeTopDirs, $depth + 1);
            } else {
                $content = $sftp->get($remoteItem);
                if ($content === false) {
                    throw new \RuntimeException('Failed to download file: '.$remoteItem);
                }
                // Write using Storage when localPath is under storage/app
                Storage::disk('local')->put($localPath.'/'.$relative, $content);
            }
        }
    }

    public function syncDirectory(SFTP $sftp, string $localPath, string $remotePath, bool $deleteRemoved = false, array $includeTopDirs = []): void
    {
        $disk = Storage::disk('local');
        $localRoot = rtrim($disk->path(''), '/');
        $localRel = $this->normalizeLocalPath($localPath, $localRoot);

        if (empty($includeTopDirs)) {
            $includeTopDirs = ['config', 'mods', 'kubejs', 'defaultconfigs', 'resourcepacks'];
        }

        foreach ($disk->allFiles($localRel) as $file) {
            $relative = ltrim(str_replace($localRel.'/', '', $file), '/');
            if (! empty($includeTopDirs)) {
                $top = explode('/', $relative)[0];
                if ($top !== '' && ! in_array($top, $includeTopDirs, true)) {
                    continue;
                }
            }
            $remoteFile = rtrim($remotePath, '/').'/'.$relative;
            $remoteDir = dirname($remoteFile);
            $sftp->mkdir($remoteDir, -1, true);

            $content = $disk->get($file);
            if (! $sftp->put($remoteFile, $content)) {
                throw new \RuntimeException('Failed to upload: '.$remoteFile);
            }
        }

        if ($deleteRemoved) {
            $this->deleteRemovedFiles($sftp, $localRel, $remotePath, $includeTopDirs);
        }
    }

    protected function deleteRemovedFiles(SFTP $sftp, string $localPath, string $remotePath, array $includeTopDirs = []): void
    {
        $disk = Storage::disk('local');
        $remoteList = $sftp->rawlist($remotePath, true);
        if (! is_array($remoteList)) {
            return;
        }

        if (empty($includeTopDirs)) {
            $includeTopDirs = ['config', 'mods', 'kubejs', 'defaultconfigs', 'resourcepacks'];
        }

        foreach ($remoteList as $name => $meta) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $remoteItem = rtrim($remotePath, '/').'/'.$name;
            $isDir = $sftp->is_dir($remoteItem);
            if (! empty($includeTopDirs) && $isDir && ! in_array($name, $includeTopDirs, true)) {
                continue;
            }
            $relative = $name;
            $localItem = $localPath.'/'.$relative;

            if (! $disk->exists($localItem)) {
                if ($isDir) {
                    $sftp->rmdir($remoteItem);
                } else {
                    $sftp->delete($remoteItem);
                }
            }
        }
    }

    public function deleteRemoved(SFTP $sftp, string $localPath, string $remotePath, array $includeTopDirs = []): void
    {
        $this->deleteRemovedFiles($sftp, $localPath, $remotePath, $includeTopDirs);
    }

    protected function ensureLocalDir(string $dir): void
    {
        if (! Storage::disk('local')->exists($dir)) {
            // If under storage/app, use Storage to create directory
            Storage::disk('local')->makeDirectory($dir);
        }
    }

    private function normalizeLocalPath(string $path, string $diskRoot): string
    {
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, $diskRoot.'/')) {
            return ltrim(substr($normalized, strlen($diskRoot)), '/');
        }

        return ltrim($normalized, '/');
    }

    // Exclude logic removed in favor of include-only top-level directories.
}
