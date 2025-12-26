<?php

declare(strict_types=1);

namespace App\Filament\Resources\SrvRecords;

use App\Filament\Resources\SrvRecords\Pages\CreateSrvRecord;
use App\Filament\Resources\SrvRecords\Pages\EditSrvRecord;
use App\Filament\Resources\SrvRecords\Pages\ListSrvRecords;
use App\Filament\Resources\SrvRecords\Schemas\SrvRecordForm;
use App\Filament\Resources\SrvRecords\Tables\SrvRecordsTable;
use App\Models\SrvRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SrvRecordResource extends Resource
{
    protected static ?string $model = SrvRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $recordTitleAttribute = 'subdomain';

    protected static ?string $modelLabel = 'DNS SRV Record';

    public static function form(Schema $schema): Schema
    {
        return SrvRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SrvRecordsTable::configure($table);
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
            'index' => ListSrvRecords::route('/'),
            'create' => CreateSrvRecord::route('/create'),
            'edit' => EditSrvRecord::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return ! empty(config('services.bunnynet.api_key')) && parent::canAccess();
    }
}
