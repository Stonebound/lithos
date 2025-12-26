<?php

declare(strict_types=1);

namespace App\Filament\Resources\SrvRecords\Pages;

use App\Filament\Resources\SrvRecords\SrvRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSrvRecords extends ListRecords
{
    protected static string $resource = SrvRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
