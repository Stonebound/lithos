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

    public function downloadDirectory(SFTP $sftp, string $remotePath, string $localPath, array $includeTopDirs = [], int $depth = 0, array $skipPatterns = []): void
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

            if ($this->shouldSkip($relative, $skipPatterns)) {
                continue;
            }

            if ($sftp->is_link($remoteItem)) {
                continue; // Skip symlinks
            }

            if ($depth === 0 && ! empty($includeTopDirs) && ! in_array($name, $includeTopDirs, true)) {
                continue;
            }

            $isDir = $sftp->is_dir($remoteItem);
            $isFile = $sftp->is_file($remoteItem);

            if ($isDir) {
                $this->downloadDirectory($sftp, $remoteItem, $localPath.'/'.$relative, $includeTopDirs, $depth + 1, $skipPatterns);
            } elseif ($isFile) {
                $content = $sftp->get($remoteItem);
                if ($content === false) {
                    throw new \RuntimeException('Failed to download file: '.$remoteItem);
                }
                // Write using Storage when localPath is under storage/app
                Storage::disk('local')->put($localPath.'/'.$relative, $content);
            }
        }
    }

    public function syncDirectory(SFTP $sftp, string $localPath, string $remotePath, bool $deleteRemoved = false, array $includeTopDirs = [], array $skipPatterns = []): void
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

            if ($this->shouldSkip($relative, $skipPatterns)) {
                continue;
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
            $this->deleteRemovedFiles($sftp, $localRel, $remotePath, $includeTopDirs, $skipPatterns);
        }
    }

    protected function deleteRemovedFiles(SFTP $sftp, string $localPath, string $remotePath, array $includeTopDirs = [], array $skipPatterns = []): void
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

            if ($sftp->is_link($remoteItem)) {
                continue;
            }

            if (! empty($includeTopDirs) && ! in_array($name, $includeTopDirs, true)) {
                continue;
            }

            $isDir = $sftp->is_dir($remoteItem);
            $relative = $name;

            if ($this->shouldSkip($relative, $skipPatterns)) {
                continue;
            }

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

    public function deleteRemoved(SFTP $sftp, string $localPath, string $remotePath, array $includeTopDirs = [], array $skipPatterns = []): void
    {
        $this->deleteRemovedFiles($sftp, $localPath, $remotePath, $includeTopDirs, $skipPatterns);
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
