<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Actions;

use App\Enums\ReleaseStatus;
use App\Filament\Concerns\HasAuthUserId;
use App\Jobs\DeployRelease;
use App\Models\Release;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class DeployAction
{
    use HasAuthUserId;

    public static function make(): Action
    {
        return Action::make('deploy')
            ->label('Deploy')
            ->icon('heroicon-o-rocket-launch')
            ->visible(fn (Release $record): bool => $record->status === ReleaseStatus::Prepared)
            ->disabled(fn (Release $record): bool => $record->isDeploying())
            ->schema([
                TextInput::make('confirmation')
                    ->label('Type "DEPLOY NOW" to confirm you understand the risks.')
                    ->required()
                    ->rule('in:DEPLOY NOW')
                    ->placeholder('DEPLOY NOW'),
            ])
            ->modalSubmitActionLabel('Deploy')
            ->modalHeading('Are you sure you want to deploy?')
            ->modalDescription('This action will deploy the release to the server. Make sure the server is stopped!!! This may overwrite files and cannot be undone. Please type "DEPLOY NOW" to confirm you have read and understood this warning.')
            ->action(function (Release $record, array $data): void {
                if (($data['confirmation'] ?? null) !== 'DEPLOY NOW') {
                    Notification::make()
                        ->title('Deployment not confirmed')
                        ->body('You must type "DEPLOY NOW" to confirm deployment.')
                        ->danger()
                        ->send();

                    return;
                }

                DeployRelease::dispatch($record->id, self::authUserId());

                Notification::make()
                    ->title('Deployment queued')
                    ->body('The deployment has been started in the background.')
                    ->info()
                    ->send();
            });
    }
}
