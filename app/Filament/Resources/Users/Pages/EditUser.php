<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('disableMFA')
                ->label('Disable MFA')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn (): bool => $this->record->update([
                    'app_authentication_secret' => null,
                    'app_authentication_recovery_codes' => null,
                ]))
                ->successNotificationTitle('MFA Disabled Successfully.')
                ->visible(fn (): bool => Auth::user()->id !== 1),
        ];
    }
}
