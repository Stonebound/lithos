<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\NormalizesStringValues;
use App\Models\OverrideRule;
use App\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Downloads the server's included directories into a local directory.
 *
 * Pterodactyl servers are snapshotted by having Wings compress the included
 * directories into one archive, which is downloaded and extracted locally.
 * Any failure on that path falls back to the recursive SFTP download.
 */
class RemoteSnapshotService
{
    use NormalizesStringValues;

    public function __construct(
        private SftpService $sftp,
        private PterodactylService $pterodactyl,
        private TarGzExtractor $extractor,
    ) {}

    /**
     * Snapshot the server into $targetDir (relative to the local disk).
     *
     * @param  (callable(string, string): void)|null  $onLog  Receives a message and a level (info, warning, error).
     */
    public function snapshot(Server $server, string $targetDir, ?callable $onLog = null): void
    {
        $onLog ??= static function (string $message, string $level): void {};

        $include = self::normalizeStringList($server->include_paths);
        if ($include === []) {
            $include = SftpService::DEFAULT_SNAPSHOT_TOP_DIRS;
        }
        $skipPatterns = OverrideRule::getSkipPatternsForServer($server);

        if ($this->pterodactyl->isPterodactylServer($server)) {
            try {
                $this->snapshotViaArchive($server, $targetDir, $include, $skipPatterns, $onLog);

                return;
            } catch (\Throwable $e) {
                $onLog('Archive snapshot failed, falling back to SFTP: '.$e->getMessage(), 'warning');
                FileUtility::deleteDirectory($targetDir);
            }
        }

        $onLog('Downloading snapshot via SFTP...', 'info');
        $connection = $this->sftp->connect($server);
        $this->sftp->downloadDirectory($connection, $server->remote_root_path, $targetDir, $include, 0, $skipPatterns);
    }

    /**
     * @param  list<string>  $include
     * @param  array<int, string>  $skipPatterns
     * @param  callable(string, string): void  $onLog
     */
    private function snapshotViaArchive(Server $server, string $targetDir, array $include, array $skipPatterns, callable $onLog): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory($targetDir);

        $root = '/'.trim($server->remote_root_path, '/');
        $topLevel = array_values(array_unique(array_filter(
            array_map(
                static fn (array $entry): ?string => $entry['is_symlink'] ? null : $entry['name'],
                $this->pterodactyl->listFiles($server, $root),
            ),
            fn (?string $name): bool => $name !== null && in_array($name, $include, true) && ! $this->sftp->shouldSkip($name, $skipPatterns),
        )));

        if ($topLevel === []) {
            $onLog('None of the included paths exist on the server; snapshot is empty.', 'warning');

            return;
        }

        $onLog('Compressing '.implode(', ', $topLevel).' on the server...', 'info');
        $archiveName = $this->pterodactyl->compressFiles($server, $root, $topLevel);
        $remoteArchive = rtrim($root, '/').'/'.$archiveName;

        $localArchive = 'tmp/snapshot-'.Str::uuid()->toString().'.tar.gz';
        $disk->makeDirectory('tmp');

        try {
            try {
                $onLog("Downloading {$archiveName}...", 'info');
                $url = $this->pterodactyl->getDownloadUrl($server, $remoteArchive);
                Http::connectTimeout(30)->timeout(3600)->sink($disk->path($localArchive))->get($url)->throw();
            } finally {
                try {
                    $this->pterodactyl->deleteFiles($server, $root, [$archiveName]);
                } catch (\Throwable $e) {
                    $onLog("Could not delete {$remoteArchive} from the server, please delete it manually: ".$e->getMessage(), 'warning');
                }
            }

            $onLog('Extracting snapshot...', 'info');
            $files = $this->extractor->extract(
                $disk->path($localArchive),
                $disk->path($targetDir),
                fn (string $relative): bool => $this->isIncluded($relative, $include, $skipPatterns),
            );
            $onLog("Archive snapshot extracted {$files} files.", 'info');
        } finally {
            $disk->delete($localArchive);
        }
    }

    /**
     * Mirror SftpService::downloadDirectory(): the top-level segment must be
     * included, and no path prefix may match a skip pattern.
     *
     * @param  list<string>  $include
     * @param  array<int, string>  $skipPatterns
     */
    private function isIncluded(string $relative, array $include, array $skipPatterns): bool
    {
        $segments = explode('/', $relative);
        if (! in_array($segments[0], $include, true)) {
            return false;
        }

        $prefix = '';
        foreach ($segments as $segment) {
            $prefix = $prefix === '' ? $segment : $prefix.'/'.$segment;
            if ($this->sftp->shouldSkip($prefix, $skipPatterns)) {
                return false;
            }
        }

        return true;
    }
}
