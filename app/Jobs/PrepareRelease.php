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

class PrepareRelease implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $timeout = 3600;

    protected int $tries = 1;

    protected int $maxExceptions = 1;

    public function __construct(
        public int $releaseId,
        public ?string $providerVersionId = null,
        public ?int $userId = null
    ) {}

    public function handle(): void
    {
        /** @var Release|null $release */
        $release = Release::query()->with('server')->find($this->releaseId);
        if (! $release) {
            return;
        }

        try {
            \App\Filament\Resources\Releases\ReleaseResource::prepareRelease($release, $this->providerVersionId);

            if ($this->userId) {
                $recipient = User::find($this->userId);
                if ($recipient) {
                    Notification::make()
                        ->title('Release prepared')
                        ->body('Overrides applied and diffs computed for release '.$release->id)
                        ->success()
                        ->sendToDatabase($recipient);
                }
            }
        } catch (\Throwable $e) {
            if ($this->userId) {
                $recipient = User::find($this->userId);
                if ($recipient) {
                    Notification::make()
                        ->title('Preparation failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->sendToDatabase($recipient);
                }
            }
            throw $e;
        }
    }
}
