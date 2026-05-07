<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Actions;

use App\Enums\ReleaseStatus;
use App\Filament\Concerns\HasAuthUserId;
use App\Jobs\PrepareRelease as PrepareReleaseJob;
use App\Models\Release;
use App\Services\FileUtility;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class PrepareAction
{
    use HasAuthUserId;

    public static function make(): Action
    {
        return Action::make('prepare')
            ->label('Prepare')
            ->icon('heroicon-o-cog-6-tooth')
            ->visible(fn (Release $record): bool => in_array($record->status, [ReleaseStatus::Draft, ReleaseStatus::Prepared], true))
            ->disabled(fn (Release $record): bool => ! FileUtility::hasSufficientDiskspace() || $record->isPreparing() || $record->isDeploying())
            ->action(function (Release $record): void {
                $providerVersionId = $record->provider_version_id ?? null;

                PrepareReleaseJob::dispatch($record->id, $providerVersionId ? (string) $providerVersionId : null, self::authUserId());

                Notification::make()
                    ->title('Preparation queued')
                    ->body('The release preparation has been started in the background.')
                    ->info()
                    ->send();
            });
    }
}
