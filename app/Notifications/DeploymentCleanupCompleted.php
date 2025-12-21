<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Release;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeploymentCleanupCompleted extends Notification
{
    use Queueable;

    public function __construct(public Release $release) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'deployment_cleanup_completed',
            'release_id' => $this->release->id,
            'server_id' => $this->release->server_id,
            'status' => $this->release->status,
            'message' => 'Deployment cleanup finished for release '.$this->release->id,
        ];
    }
}
