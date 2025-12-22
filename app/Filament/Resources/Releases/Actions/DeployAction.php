<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Actions;

use App\Enums\ReleaseStatus;
use App\Jobs\DeployRelease;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class DeployAction
{
    public static function make(): Action
    {
        return Action::make('deploy')
            ->label('Deploy')
            ->icon('heroicon-o-rocket-launch')
            ->visible(fn ($record): bool => $record->status === ReleaseStatus::Prepared)
            ->disabled(fn ($record): bool => $record->isDeploying())
            ->requiresConfirmation()
            ->action(function ($record): void {
                DeployRelease::dispatch($record->id, Auth::id());

                Notification::make()
                    ->title('Deployment queued')
                    ->body('The deployment has been started in the background.')
                    ->info()
                    ->send();
            });
    }
}
