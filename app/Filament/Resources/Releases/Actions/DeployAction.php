<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Actions;

use App\Enums\ReleaseStatus;
use App\Jobs\DeployRelease as DeployReleaseJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class DeployAction
{
    public static function make(): Action
    {
        return Action::make('deploy')
            ->label('Deploy')
            ->icon('heroicon-o-rocket-launch')
            ->visible(fn ($record): bool => $record->status === ReleaseStatus::Prepared)
            ->disabled(fn ($record): bool => Cache::lock(UniqueLock::getKey(new DeployReleaseJob($record->id)), 1)->get(fn () => true) === false)
            ->requiresConfirmation()
            ->action(function ($record): void {
                DeployReleaseJob::dispatch($record->id, Auth::id());

                Notification::make()
                    ->title('Deployment queued')
                    ->body('The deployment has been started in the background.')
                    ->info()
                    ->send();
            });
    }
}
