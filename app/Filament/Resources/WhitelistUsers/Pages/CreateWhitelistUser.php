<?php

declare(strict_types=1);

namespace App\Filament\Resources\WhitelistUsers\Pages;

use App\Filament\Resources\WhitelistUsers\WhitelistUserResource;
use App\Models\WhitelistUser;
use App\Services\MinecraftApi;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateWhitelistUser extends CreateRecord
{
    protected static string $resource = WhitelistUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $name = $data['username'] ?? null;
        if (! $name) {
            return $data;
        }

        // fetch uuid from Mojang profile lookup API
        $service = app(MinecraftApi::class);
        $uuid = $service->uuidForName($name);
        if (! $uuid) {
            Notification::make()
                ->title('Could not resolve UUID for that username.')
                ->danger()
                ->send();
            $this->halt();
        }

        if (WhitelistUser::where('uuid', $uuid)->exists()) {
            Notification::make()
                ->title('That user is already whitelisted.')
                ->danger()
                ->send();
            $this->halt();
        }

        $data['uuid'] = $uuid;

        return $data;
    }
}
