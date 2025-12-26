<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\Release;
use App\Models\User;
use App\Services\AuditService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeployRelease implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    protected int $maxExceptions = 1;

    public int $uniqueFor = 3600;

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

        $auditService = app(AuditService::class);
        $auditService->log('deploy', $release);

        try {
            ReleaseResource::deployRelease($release);

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
            ReleaseResource::log($release, 'Deployment failed: '.$e->getMessage(), 'error');
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

    public function uniqueId(): string
    {
        return 'release:'.$this->releaseId.':deploy';
    }
}
