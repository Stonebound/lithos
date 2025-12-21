<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Release;
use App\Services\SftpService;
use Illuminate\Console\Command;

class DeployRelease extends Command
{
    protected $signature = 'modpack:deploy {release_id} {--delete-removed}';

    protected $description = 'Deploy prepared release to server via SFTP.';

    public function handle(SftpService $sftpService): int
    {
        $releaseId = (int) $this->argument('release_id');
        /** @var Release|null $release */
        $release = Release::query()->with('server')->find($releaseId);
        if (! $release) {
            $this->error('Release not found: '.$releaseId);

            return self::FAILURE;
        }

        if (! $release->prepared_path || ! is_dir($release->prepared_path)) {
            $this->error('Prepared path missing. Run modpack:prepare first.');

            return self::FAILURE;
        }

        $server = $release->server;
        $sftp = $sftpService->connect($server);

        $this->info('Syncing files to remote...');
        $sftpService->syncDirectory(
            $sftp,
            $release->prepared_path,
            rtrim($server->remote_root_path, '/'),
            (bool) $this->option('delete-removed'),
            $server->include_paths ?? []
        );

        $release->update(['status' => 'deployed']);
        $this->info('Deployment complete for release '.$release->id);

        return self::SUCCESS;
    }
}
