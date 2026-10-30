<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Server;
use App\Models\User;
use App\Services\RemoteSnapshotService;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SnapshotServer implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $serverId, public ?int $userId = null) {}

    public function handle(): void
    {
        /** @var Server|null $server */
        $server = Server::query()->find($this->serverId);
        if (! $server) {
            return;
        }

        $target = 'servers/'.$server->id.'/snapshot';
        Storage::disk('local')->makeDirectory($target);

        /** @var RemoteSnapshotService $snapshotSvc */
        $snapshotSvc = app(RemoteSnapshotService::class);
        $snapshotSvc->snapshot($server, $target, static function (string $message, string $level) use ($server): void {
            Log::log($level, $message, ['server_id' => $server->id]);
        });

        if ($this->userId) {
            $recipient = User::query()->find($this->userId);
            if ($recipient) {
                FilamentNotification::make()
                    ->title('Snapshot complete')
                    ->body('Saved to: '.$target)
                    ->sendToDatabase($recipient);
            }
        }
    }
}
