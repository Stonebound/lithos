<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\RelationManagers;

use App\Filament\Resources\FileChanges\FileChangeResource;
use App\Filament\Resources\FileChanges\Tables\FileChangesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class FileChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'fileChanges';

    protected static ?string $relatedResource = FileChangeResource::class;

    public function table(Table $table): Table
    {
        return FileChangesTable::configure($table);
    }
}
