<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Server;
use App\Services\SftpService;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class SnapshotServer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $serverId, public ?int $userId = null) {}

    public function handle(): void
    {
        /** @var Server|null $server */
        $server = Server::query()->find($this->serverId);
        if (! $server) {
            return;
        }

        /** @var SftpService $sftpSvc */
        $sftpSvc = app(SftpService::class);
        $sftp = $sftpSvc->connect($server);
        $target = 'servers/'.$server->id.'/snapshot';
        Storage::disk('local')->makeDirectory($target);

        $paths = $server->include_paths;

        $skipPatterns = \App\Models\OverrideRule::getSkipPatternsForServer($server);

        $sftpSvc->downloadDirectory($sftp, $server->remote_root_path, $target, $paths, 0, $skipPatterns);

        if ($this->userId) {
            $recipient = \App\Models\User::query()->find($this->userId);
            if ($recipient) {
                FilamentNotification::make()
                    ->title('Snapshot complete')
                    ->body('Saved to: '.$target)
                    ->sendToDatabase($recipient);
            }
        }
    }
}
