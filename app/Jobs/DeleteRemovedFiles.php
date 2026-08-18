<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Concerns\NormalizesStringValues;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\OverrideRule;
use App\Models\Release;
use App\Services\SftpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class DeleteRemovedFiles implements ShouldQueue
{
    use NormalizesStringValues;
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $releaseId) {}

    public function handle(): void
    {
        /** @var Release|null $release */
        $release = Release::query()->with('server')->find($this->releaseId);
        if (! $release || ! $release->prepared_path) {
            return;
        }

        $preparedPath = $release->prepared_path;

        if (Storage::disk('local')->allFiles($preparedPath) === []) {
            ReleaseResource::log($release, 'Prepared directory is empty or missing. Aborting cleanup to avoid deleting remote files.', 'error');

            throw new \RuntimeException('Prepared directory is empty or missing. Aborting cleanup to avoid deleting remote files.');
        }

        ReleaseResource::log($release, 'Starting cleanup of removed files...');

        $server = $release->server;
        /** @var SftpService $sftpSvc */
        $sftpSvc = app(SftpService::class);
        $sftp = $sftpSvc->connect($server);

        $include = self::normalizeStringList($server->include_paths);

        $skipPatterns = OverrideRule::getSkipPatternsForServer($server);

        $sftpSvc->deleteRemoved($sftp, $preparedPath, $server->remote_root_path, $include, $skipPatterns, function (mixed $file) use ($release): void {
            ReleaseResource::log($release, 'Deleted: '.self::normalizeStringValue($file, 'unknown'));
        });

        ReleaseResource::log($release, 'Cleanup of removed files completed.');
    }
}
