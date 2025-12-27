<?php

declare(strict_types=1);

namespace App\Filament\Resources\WhitelistUsers\Pages;

use App\Filament\Resources\WhitelistUsers\WhitelistUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWhitelistUser extends ViewRecord
{
    protected static string $resource = WhitelistUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
