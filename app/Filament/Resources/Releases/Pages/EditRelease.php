<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Pages;

use App\Enums\ReleaseStatus;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Jobs\DeployRelease;
use App\Jobs\PrepareRelease;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditRelease extends EditRecord
{
    protected static string $resource = ReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prepare')
                ->label('Prepare')
                ->visible(fn (): bool => in_array($this->record->status, [ReleaseStatus::Draft, ReleaseStatus::Prepared]))
                ->action(function (): void {
                    $state = $this->form->getState();
                    $providerVersionId = $state['provider_version_id'] ?? $this->record->provider_version_id;

                    PrepareRelease::dispatch(
                        $this->record->id,
                        $providerVersionId ? (string) $providerVersionId : null,
                        Auth::id()
                    );

                    Notification::make()
                        ->title('Preparation queued')
                        ->body('The release preparation has been started in the background.')
                        ->info()
                        ->send();
                }),
            Action::make('deploy')
                ->label('Deploy')
                ->visible(fn (): bool => $this->record->status === ReleaseStatus::Prepared)
                ->requiresConfirmation()
                ->action(function (): void {
                    DeployRelease::dispatch($this->record->id, Auth::id());

                    Notification::make()
                        ->title('Deployment queued')
                        ->body('The deployment has been started in the background.')
                        ->info()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
