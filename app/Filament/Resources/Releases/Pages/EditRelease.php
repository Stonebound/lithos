<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Pages;

use App\Filament\Resources\Releases\ReleaseResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Toggle;
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
                ->action(function (): void {
                    $state = $this->form->getState();
                    $providerVersionId = $state['provider_version_id'] ?? null;
                    ReleaseResource::prepareRelease($this->record, $providerVersionId ? (string) $providerVersionId : null);
                }),
            Action::make('deploy')
                ->label('Deploy')
                ->visible(fn (): bool => (function () {
                    $u = Auth::user();

                    return $u ? in_array($u->role ?? 'viewer', ['maintainer', 'admin'], true) : false;
                })())
                ->form([
                    Toggle::make('delete_removed')->label('Delete removed files')->default(false),
                ])
                ->action(function (array $data): void {
                    $deleteRemoved = (bool) ($data['delete_removed'] ?? false);
                    ReleaseResource::deployRelease($this->record, $deleteRemoved);
                }),
            DeleteAction::make(),
        ];
    }
}
