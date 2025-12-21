<?php

declare(strict_types=1);

namespace App\Filament\Resources\FileChanges\Pages;

use App\Filament\Resources\FileChanges\FileChangeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFileChange extends EditRecord
{
    protected static string $resource = FileChangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
