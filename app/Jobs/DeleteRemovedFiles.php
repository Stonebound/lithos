<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Release;
use App\Services\SftpService;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteRemovedFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $releaseId, public ?int $userId = null) {}

    public function handle(): void
    {
        /** @var Release|null $release */
        $release = Release::query()->with('server')->find($this->releaseId);
        if (! $release || ! $release->prepared_path) {
            return;
        }

        $server = $release->server;
        /** @var SftpService $sftpSvc */
        $sftpSvc = app(SftpService::class);
        $sftp = $sftpSvc->connect($server);

        $include = is_array($server->include_paths)
            ? $server->include_paths
            : array_values(array_filter(array_map(fn ($l) => trim($l), preg_split('/\r\n|\r|\n/', (string) ($server->include_paths ?? '')))));

        $sftpSvc->deleteRemoved($sftp, $release->prepared_path, $server->remote_root_path, $include);

        if ($this->userId) {
            $recipient = \App\Models\User::query()->find($this->userId);
            if ($recipient) {
                FilamentNotification::make()
                    ->title('Deployment cleanup finished')
                    ->body('Release '.$release->id.' cleanup is complete.')
                    ->sendToDatabase($recipient);
            }
        }
    }
}
