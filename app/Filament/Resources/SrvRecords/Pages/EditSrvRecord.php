<?php

declare(strict_types=1);

namespace App\Filament\Resources\SrvRecords\Pages;

use App\Filament\Resources\SrvRecords\SrvRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSrvRecord extends EditRecord
{
    protected static string $resource = SrvRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
