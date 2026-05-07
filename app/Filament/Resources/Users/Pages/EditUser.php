<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\HasAuthUserId;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use HasAuthUserId;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('disableMFA')
                ->label('Disable MFA')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): bool {
                    /** @var User $user */
                    $user = $this->record;

                    return $user->update([
                        'app_authentication_secret' => null,
                        'app_authentication_recovery_codes' => null,
                    ]);
                })
                ->successNotificationTitle('MFA Disabled Successfully.')
                ->visible(fn (): bool => self::authUserId() !== 1),
        ];
    }
}
