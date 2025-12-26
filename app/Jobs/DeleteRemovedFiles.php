<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\OverrideRule;
use App\Models\Release;
use App\Services\SftpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteRemovedFiles implements ShouldQueue
{
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

        ReleaseResource::log($release, 'Starting cleanup of removed files...');

        $server = $release->server;
        /** @var SftpService $sftpSvc */
        $sftpSvc = app(SftpService::class);
        $sftp = $sftpSvc->connect($server);

        $include = $server->include_paths ?? [];

        $skipPatterns = OverrideRule::getSkipPatternsForServer($server);

        $sftpSvc->deleteRemoved($sftp, $release->prepared_path, $server->remote_root_path, $include, $skipPatterns, function ($action, $file) use ($release) {
            ReleaseResource::log($release, "Deleted: {$file}");
        });

        ReleaseResource::log($release, 'Cleanup of removed files completed.');
    }
}
