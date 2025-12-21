<?php

declare(strict_types=1);

namespace App\Filament\Resources\FileChanges\Pages;

use App\Filament\Resources\FileChanges\FileChangeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFileChange extends CreateRecord
{
    protected static string $resource = FileChangeResource::class;
}
