<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Actions;

use App\Enums\ReleaseStatus;
use App\Jobs\PrepareRelease as PrepareReleaseJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class PrepareAction
{
    public static function make(): Action
    {

        return Action::make('prepare')
            ->label('Prepare')
            ->icon('heroicon-o-cog-6-tooth')
            ->visible(fn ($record): bool => in_array($record->status, [ReleaseStatus::Draft, ReleaseStatus::Prepared]))
            ->disabled(fn ($record): bool => $record->isPreparing() || $record->isDeploying())
            ->action(function ($record): void {
                $providerVersionId = $record->provider_version_id ?? null;

                PrepareReleaseJob::dispatch($record->id, $providerVersionId ? (string) $providerVersionId : null, Auth::id());

                Notification::make()
                    ->title('Preparation queued')
                    ->body('The release preparation has been started in the background.')
                    ->info()
                    ->send();
            });
    }
}
