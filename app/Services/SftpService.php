<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReleaseLog;
use App\Models\Server;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Storage;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;

class SftpService
{
    public function connect(Server $server): SFTP
    {
        return $this->connectFromConfig($this->serverConnectionConfig($server));
    }

    public function downloadDirectory(SFTP $sftp, string $remotePath, string $localPath, array $includeTopDirs = [], int $depth = 0, array $skipPatterns = [], string $accumulatedPath = ''): void
    {
        $items = $sftp->rawlist($remotePath);
        if (! is_array($items)) {
            throw new \RuntimeException('Failed to list remote path: '.$remotePath);
        }

        if (empty($includeTopDirs)) {
            $includeTopDirs = ['config', 'mods', 'kubejs', 'defaultconfigs', 'datapacks', 'resourcepacks'];
        }

        $disk = Storage::disk('local');
        $diskRoot = $disk->path('');

        foreach ($items as $name => $meta) {
            $name = (string) $name;
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

            if ($depth === 0 && ! in_array($name, $includeTopDirs, true)) {
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

    public function syncServerDirectory(Server $server, string $localPath, string $remotePath, array $skipPatterns = [], ?int $releaseId = null): array
    {
        $relativeFiles = $this->collectRelativeFiles($localPath, $skipPatterns);

        if ($relativeFiles === []) {
            return [
                'failed_workers' => 0,
                'connections' => 0,
                'uploaded_files' => 0,
                'workers' => [],
            ];
        }

        $connections = min($this->parallelUploadConnections(), count($relativeFiles));
        $parallelDriver = $this->parallelUploadDriver();

        if ($connections <= 1) {
            $sftp = $this->connect($server);
            $uploadedFiles = $this->syncDirectory(
                $sftp,
                $localPath,
                $remotePath,
                [],
                $this->buildProgressLogger($releaseId, 1, $relativeFiles),
                $relativeFiles,
            );

            return [
                'failed_workers' => 0,
                'connections' => 1,
                'uploaded_files' => $uploadedFiles,
                'workers' => [
                    $this->buildWorkerSummary(1, $relativeFiles, $uploadedFiles),
                ],
            ];
        }

        $serverConfig = $this->serverConnectionConfig($server);
        $tasks = [];

        foreach ($this->chunkRelativeFiles($relativeFiles, $connections) as $index => $chunk) {
            $worker = $index + 1;

            $tasks[] = static fn (): array => app(self::class)->uploadRelativeFileBatch(
                $serverConfig,
                $localPath,
                $remotePath,
                $chunk,
                $worker,
                $releaseId,
            );
        }

        $results = Concurrency::driver($parallelDriver)->run($tasks);
        usort($results, fn (array $left, array $right): int => $left['worker'] <=> $right['worker']);

        return [
            'connections' => count($results),
            'failed_workers' => count(array_filter($results, fn (array $result): bool => $result['status'] === 'failed')),
            'uploaded_files' => array_sum(array_column($results, 'uploaded_files')),
            'workers' => $results,
        ];
    }

    public function syncDirectory(SFTP $sftp, string $localPath, string $remotePath, array $skipPatterns = [], ?callable $onProgress = null, ?array $relativeFiles = null): int
    {
        $disk = Storage::disk('local');
        $localRel = $this->normalizeLocalPath($localPath, rtrim($disk->path(''), '/'));

        $relativeFiles ??= $this->collectRelativeFiles($localPath, $skipPatterns);

        $knownDirs = [];

        foreach ($relativeFiles as $relative) {
            if ($this->shouldSkip($relative, $skipPatterns)) {
                continue;
            }

            $file = $localRel === '' ? $relative : $localRel.'/'.$relative;
            $remoteFile = rtrim($remotePath, '/').'/'.$relative;
            $remoteDir = dirname($remoteFile);

            if (! isset($knownDirs[$remoteDir])) {
                if (! $sftp->is_dir($remoteDir)) {
                    if (! $sftp->mkdir($remoteDir, -1, true) && ! $sftp->is_dir($remoteDir)) {
                        throw new \RuntimeException('Failed to create remote directory: '.$remoteDir);
                    }
                }
                $knownDirs[$remoteDir] = true;
            }

            if (! $sftp->put($remoteFile, $disk->path($file), SFTP::SOURCE_LOCAL_FILE)) {
                throw new \RuntimeException('Failed to upload: '.$remoteFile);
            }

            if ($onProgress) {
                $onProgress('upload', $relative);
            }
        }

        return count($relativeFiles);
    }

    protected function deleteRemovedFiles(SFTP $sftp, string $localPath, string $remotePath, array $skipPatterns = [], array $includeTopDirs = [], int $depth = 0, string $accumulatedPath = '', ?callable $onProgress = null): void
    {
        $disk = Storage::disk('local');
        $remoteList = $sftp->rawlist($remotePath);
        if (! is_array($remoteList)) {
            return;
        }

        if ($depth === 0 && empty($includeTopDirs)) {
            $includeTopDirs = ['config', 'kubejs', 'datapacks', 'resourcepacks', 'mods'];
        }

        foreach ($remoteList as $name => $meta) {
            $name = (string) $name;
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
                if ($onProgress) {
                    $onProgress('delete', $currentRelative);
                }
                $sftp->delete($remoteItem, true);
            } elseif ($type === 2) { // NET_SFTP_TYPE_DIRECTORY
                $this->deleteRemovedFiles($sftp, $localItem, $remoteItem, $skipPatterns, $includeTopDirs, $depth + 1, $currentRelative, $onProgress);
            }
        }
    }

    public function deleteRemoved(SFTP $sftp, string $localPath, string $remotePath, array $includeTopDirs = [], array $skipPatterns = [], ?callable $onProgress = null): void
    {
        $this->deleteRemovedFiles($sftp, $localPath, $remotePath, $skipPatterns, $includeTopDirs, 0, '', $onProgress);
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

    public function uploadRelativeFileBatch(array $serverConfig, string $localPath, string $remotePath, array $relativeFiles, int $worker, ?int $releaseId = null): array
    {
        try {
            $sftp = $this->connectFromConfig($serverConfig);
            $uploadedFiles = $this->syncDirectory(
                $sftp,
                $localPath,
                $remotePath,
                [],
                $this->buildProgressLogger($releaseId, $worker, $relativeFiles),
                $relativeFiles,
            );

            return $this->buildWorkerSummary($worker, $relativeFiles, $uploadedFiles);
        } catch (\Throwable $exception) {
            return $this->buildWorkerFailureSummary($worker, $relativeFiles, $exception);
        }
    }

    protected function collectRelativeFiles(string $localPath, array $skipPatterns = []): array
    {
        $disk = Storage::disk('local');
        $localRoot = rtrim($disk->path(''), '/');
        $localRel = $this->normalizeLocalPath($localPath, $localRoot);

        $relativeFiles = [];

        foreach ($disk->allFiles($localRel) as $file) {
            $relative = ltrim(str_replace($localRel.'/', '', $file), '/');

            if ($this->shouldSkip($relative, $skipPatterns)) {
                continue;
            }

            $relativeFiles[] = $relative;
        }

        sort($relativeFiles);

        return $relativeFiles;
    }

    protected function chunkRelativeFiles(array $relativeFiles, int $connections): array
    {
        $chunkCount = max(1, min($connections, count($relativeFiles)));
        $chunkSize = (int) ceil(count($relativeFiles) / $chunkCount);

        return array_values(array_chunk($relativeFiles, max(1, $chunkSize)));
    }

    protected function parallelUploadConnections(): int
    {
        return max(1, (int) config('services.sftp.parallel_upload_connections', 10));
    }

    protected function parallelUploadDriver(): string
    {
        return (string) config('services.sftp.parallel_upload_driver', 'process');
    }

    /**
     * @param  array<int, string>  $relativeFiles
     */
    protected function buildProgressLogger(?int $releaseId, int $worker, array $relativeFiles): ?callable
    {
        if (! $releaseId || $relativeFiles === []) {
            return null;
        }

        $totalFiles = count($relativeFiles);
        $completedFiles = 0;
        $lastLoggedAt = microtime(true);
        $logEveryFiles = max(1, (int) config('services.sftp.progress_every_files', 100));
        $logEverySeconds = max(0.0, (float) config('services.sftp.progress_every_seconds', 3));

        return static function (string $action, string $relativePath) use ($releaseId, $worker, $totalFiles, &$completedFiles, &$lastLoggedAt, $logEveryFiles, $logEverySeconds): void {
            $completedFiles++;
            $now = microtime(true);

            $shouldLog = $completedFiles === $totalFiles
                || $completedFiles % $logEveryFiles === 0
                || (($now - $lastLoggedAt) >= $logEverySeconds);

            if (! $shouldLog) {
                return;
            }

            ReleaseLog::query()->create([
                'release_id' => $releaseId,
                'level' => 'info',
                'message' => sprintf(
                    'Worker %d progress: %d/%d files (%.1f%%), latest: %s',
                    $worker,
                    $completedFiles,
                    $totalFiles,
                    ($completedFiles / $totalFiles) * 100,
                    $relativePath,
                ),
            ]);

            $lastLoggedAt = $now;
        };
    }

    /**
     * @return array{host: string, port: int, username: string, auth_type: string, password: ?string, private_key_path: ?string}
     */
    protected function serverConnectionConfig(Server $server): array
    {
        return [
            'host' => $server->host,
            'port' => $server->port,
            'username' => $server->username,
            'auth_type' => $server->auth_type,
            'password' => $server->password,
            'private_key_path' => $server->private_key_path,
        ];
    }

    /**
     * @param  array{host: string, port: int, username: string, auth_type: string, password: ?string, private_key_path: ?string}  $serverConfig
     */
    protected function connectFromConfig(array $serverConfig): SFTP
    {
        $sftp = new SFTP($serverConfig['host'], $serverConfig['port']);

        if ($serverConfig['auth_type'] === 'password') {
            if (! $sftp->login($serverConfig['username'], $serverConfig['password'] ?? '')) {
                throw new \RuntimeException('SFTP password login failed');
            }

            return $sftp;
        }

        $privateKeyPath = $serverConfig['private_key_path'];
        if (! $privateKeyPath) {
            throw new \RuntimeException('SFTP key login failed: missing private key path');
        }

        $keyContents = Storage::disk('local')->get($privateKeyPath);
        $key = PublicKeyLoader::load($keyContents);

        if (! $sftp->login($serverConfig['username'], $key)) {
            throw new \RuntimeException('SFTP key login failed');
        }

        return $sftp;
    }

    /**
     * @param  array<int, string>  $relativeFiles
     * @return array{worker: int, status: string, uploaded_files: int, first_file: ?string, last_file: ?string, failed_file: ?string, error: ?string}
     */
    protected function buildWorkerSummary(int $worker, array $relativeFiles, int $uploadedFiles): array
    {
        return [
            'worker' => $worker,
            'status' => 'success',
            'uploaded_files' => $uploadedFiles,
            'first_file' => $relativeFiles[0] ?? null,
            'last_file' => $relativeFiles[array_key_last($relativeFiles)] ?? null,
            'failed_file' => null,
            'error' => null,
        ];
    }

    /**
     * @param  array<int, string>  $relativeFiles
     * @return array{worker: int, status: string, uploaded_files: int, first_file: ?string, last_file: ?string, failed_file: ?string, error: ?string}
     */
    protected function buildWorkerFailureSummary(int $worker, array $relativeFiles, \Throwable $exception): array
    {
        return [
            'worker' => $worker,
            'status' => 'failed',
            'uploaded_files' => 0,
            'first_file' => $relativeFiles[0] ?? null,
            'last_file' => $relativeFiles[array_key_last($relativeFiles)] ?? null,
            'failed_file' => $this->extractFailedFile($exception->getMessage()),
            'error' => $exception->getMessage(),
        ];
    }

    protected function extractFailedFile(string $message): ?string
    {
        if (preg_match('/Failed to upload: .+\/(.+)$/', $message, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/Failed to create remote directory: .+\/(.+)$/', $message, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
