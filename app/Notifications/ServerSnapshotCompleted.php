<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ServerSnapshotCompleted extends Notification
{
    use Queueable;

    public function __construct(public Server $server, public string $path) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'snapshot_completed',
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'path' => $this->path,
            'message' => 'Snapshot complete for '.$this->server->name,
        ];
    }
}
