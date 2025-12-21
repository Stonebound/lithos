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

    public function downloadDirectory(SFTP $sftp, string $remotePath, string $localPath, array $includeTopDirs = [], int $depth = 0, array $skipPatterns = [], string $accumulatedPath = ''): void
    {
        $items = $sftp->rawlist($remotePath);
        if (! is_array($items)) {
            throw new \RuntimeException('Failed to list remote path: '.$remotePath);
        }

        if (empty($includeTopDirs)) {
            $includeTopDirs = ['config', 'mods', 'kubejs', 'defaultconfigs', 'resourcepacks'];
        }

        $disk = Storage::disk('local');
        $diskRoot = $disk->path('');

        foreach ($items as $name => $meta) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $remoteItem = rtrim($remotePath, '/').'/'.$name;
            $currentRelative = $accumulatedPath === '' ? $name : $accumulatedPath.'/'.$name;

            if ($this->shouldSkip($currentRelative, $skipPatterns)) {
                continue;
            }

            // Use metadata from rawlist to avoid extra network calls
            $type = $meta['type'] ?? null;
            if ($type === 3) { // NET_SFTP_TYPE_SYMLINK
                continue;
            }

            if ($depth === 0 && ! empty($includeTopDirs) && ! in_array($name, $includeTopDirs, true)) {
                continue;
            }

            if ($type === 2) { // NET_SFTP_TYPE_DIRECTORY
                $this->downloadDirectory($sftp, $remoteItem, $localPath.'/'.$name, $includeTopDirs, $depth + 1, $skipPatterns, $currentRelative);
            } elseif ($type === 1) { // NET_SFTP_TYPE_REGULAR
                $localFile = $localPath.'/'.$name;
                $localDir = dirname($localFile);
                $fullLocalDir = $diskRoot.'/'.ltrim($localDir, '/');

                if (! is_dir($fullLocalDir)) {
                    $disk->makeDirectory($localDir);
                }

                $fullLocalFile = $diskRoot.'/'.ltrim($localFile, '/');
                if (! $sftp->get($remoteItem, $fullLocalFile)) {
                    throw new \RuntimeException('Failed to download file: '.$remoteItem);
                }
            }
        }
    }

    public function syncDirectory(SFTP $sftp, string $localPath, string $remotePath, array $skipPatterns = []): void
    {
        $disk = Storage::disk('local');
        $localRoot = rtrim($disk->path(''), '/');
        $localRel = $this->normalizeLocalPath($localPath, $localRoot);

        $knownDirs = [];

        foreach ($disk->allFiles($localRel) as $file) {
            $relative = ltrim(str_replace($localRel.'/', '', $file), '/');

            if ($this->shouldSkip($relative, $skipPatterns)) {
                continue;
            }

            $remoteFile = rtrim($remotePath, '/').'/'.$relative;
            $remoteDir = dirname($remoteFile);

            if (! isset($knownDirs[$remoteDir])) {
                if (! $sftp->is_dir($remoteDir)) {
                    $sftp->mkdir($remoteDir, -1, true);
                }
                $knownDirs[$remoteDir] = true;
            }

            if (! $sftp->put($remoteFile, $disk->path($file), \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE)) {
                throw new \RuntimeException('Failed to upload: '.$remoteFile);
            }
        }
    }

    protected function deleteRemovedFiles(SFTP $sftp, string $localPath, string $remotePath, array $skipPatterns = [], array $includeTopDirs = [], int $depth = 0, string $accumulatedPath = ''): void
    {
        $disk = Storage::disk('local');
        $remoteList = $sftp->rawlist($remotePath);
        if (! is_array($remoteList)) {
            return;
        }

        if ($depth === 0 && empty($includeTopDirs)) {
            $includeTopDirs = ['config', 'kubejs', 'resourcepacks', 'mods'];
        }

        foreach ($remoteList as $name => $meta) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            if ($depth === 0 && ! empty($includeTopDirs) && ! in_array($name, $includeTopDirs, true)) {
                continue;
            }

            $remoteItem = rtrim($remotePath, '/').'/'.$name;
            $currentRelative = $accumulatedPath === '' ? $name : $accumulatedPath.'/'.$name;

            if ($this->shouldSkip($currentRelative, $skipPatterns)) {
                continue;
            }

            $type = $meta['type'] ?? null;
            if ($type === 3) { // NET_SFTP_TYPE_SYMLINK
                continue;
            }

            $localItem = $localPath.'/'.$name;

            if (! $disk->exists($localItem)) {
                $sftp->delete($remoteItem, true);
            } elseif ($type === 2) { // NET_SFTP_TYPE_DIRECTORY
                $this->deleteRemovedFiles($sftp, $localItem, $remoteItem, $skipPatterns, $includeTopDirs, $depth + 1, $currentRelative);
            }
        }
    }

    public function deleteRemoved(SFTP $sftp, string $localPath, string $remotePath, array $includeTopDirs = [], array $skipPatterns = []): void
    {
        $this->deleteRemovedFiles($sftp, $localPath, $remotePath, $skipPatterns, $includeTopDirs);
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
}
