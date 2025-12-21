<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Server;
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
            $key = PublicKeyLoader::load(file_get_contents($server->private_key_path));
            if (! $sftp->login($server->username, $key)) {
                throw new \RuntimeException('SFTP key login failed');
            }
        }

        return $sftp;
    }

    public function downloadDirectory(SFTP $sftp, string $remotePath, string $localPath, array $includeTopDirs = [], int $depth = 0): void
    {
        $this->ensureLocalDir($localPath);
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

            if ($isDir) {
                $this->downloadDirectory($sftp, $remoteItem, $localPath.'/'.$relative, $includeTopDirs, $depth + 1);
            } else {
                $this->ensureLocalDir(dirname($localPath.'/'.$relative));
                $content = $sftp->get($remoteItem);
                if ($content === false) {
                    throw new \RuntimeException('Failed to download file: '.$remoteItem);
                }
                file_put_contents($localPath.'/'.$relative, $content);
            }
        }
    }

    public function syncDirectory(SFTP $sftp, string $localPath, string $remotePath, bool $deleteRemoved = false, array $includeTopDirs = []): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($localPath, \FilesystemIterator::SKIP_DOTS)
        );

        if (empty($includeTopDirs)) {
            $includeTopDirs = ['config', 'mods', 'kubejs', 'defaultconfigs', 'resourcepacks'];
        }

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $relative = str_replace($localPath.'/', '', $fileInfo->getPathname());
            if (! empty($includeTopDirs)) {
                $top = explode('/', $relative)[0];
                if ($top !== '' && ! in_array($top, $includeTopDirs, true)) {
                    continue;
                }
            }
            $remoteFile = rtrim($remotePath, '/').'/'.$relative;
            $remoteDir = dirname($remoteFile);
            $sftp->mkdir($remoteDir, -1, true);
            $content = file_get_contents($fileInfo->getPathname());
            if (! $sftp->put($remoteFile, $content)) {
                throw new \RuntimeException('Failed to upload: '.$remoteFile);
            }
        }

        if ($deleteRemoved) {
            $this->deleteRemovedFiles($sftp, $localPath, $remotePath, $includeTopDirs);
        }
    }

    protected function deleteRemovedFiles(SFTP $sftp, string $localPath, string $remotePath, array $includeTopDirs = []): void
    {
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

            if (! file_exists($localItem)) {
                if ($isDir) {
                    $sftp->rmdir($remoteItem);
                } else {
                    $sftp->delete($remoteItem);
                }
            }
        }
    }

    protected function ensureLocalDir(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    // Exclude logic removed in favor of include-only top-level directories.
}
