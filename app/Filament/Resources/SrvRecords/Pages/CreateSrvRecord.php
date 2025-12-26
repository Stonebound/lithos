<?php

declare(strict_types=1);

namespace App\Filament\Resources\SrvRecords\Pages;

use App\Filament\Resources\SrvRecords\SrvRecordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSrvRecord extends CreateRecord
{
    protected static string $resource = SrvRecordResource::class;
}
