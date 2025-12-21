<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Release;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeployRelease implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $releaseId,
        public ?int $userId = null
    ) {}

    public function handle(): void
    {
        /** @var Release|null $release */
        $release = Release::query()->with('server')->find($this->releaseId);
        if (! $release || ! $release->prepared_path) {
            return;
        }

        try {
            \App\Filament\Resources\Releases\ReleaseResource::deployRelease($release, true);

            if ($this->userId) {
                $recipient = User::find($this->userId);
                if ($recipient) {
                    Notification::make()
                        ->title('Deployment complete')
                        ->body('Sync complete. Removal of deleted files queued for release '.$release->id)
                        ->success()
                        ->sendToDatabase($recipient);
                }
            }
        } catch (\Throwable $e) {
            if ($this->userId) {
                $recipient = User::find($this->userId);
                if ($recipient) {
                    Notification::make()
                        ->title('Deployment failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->sendToDatabase($recipient);
                }
            }
            throw $e;
        }
    }
}
