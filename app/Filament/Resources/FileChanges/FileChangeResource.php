<?php

declare(strict_types=1);

namespace App\Filament\Resources\FileChanges;

use App\Filament\Resources\FileChanges\Pages\CreateFileChange;
use App\Filament\Resources\FileChanges\Pages\EditFileChange;
use App\Filament\Resources\FileChanges\Pages\ListFileChanges;
use App\Filament\Resources\FileChanges\Schemas\FileChangeForm;
use App\Filament\Resources\FileChanges\Tables\FileChangesTable;
use App\Models\FileChange;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FileChangeResource extends Resource
{
    protected static ?string $model = FileChange::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function form(Schema $schema): Schema
    {
        return FileChangeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FileChangesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFileChanges::route('/'),
            'create' => CreateFileChange::route('/create'),
            'edit' => EditFileChange::route('/{record}/edit'),
        ];
    }
}
