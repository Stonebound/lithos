<?php

declare(strict_types=1);

namespace App\Filament\Resources\WhitelistUsers;

use App\Filament\Resources\WhitelistUsers\Pages\CreateWhitelistUser;
use App\Filament\Resources\WhitelistUsers\Pages\ListWhitelistUsers;
use App\Filament\Resources\WhitelistUsers\Pages\ViewWhitelistUser;
use App\Filament\Resources\WhitelistUsers\Schemas\WhitelistUserForm;
use App\Filament\Resources\WhitelistUsers\Tables\WhitelistUsersTable;
use App\Models\WhitelistUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WhitelistUserResource extends Resource
{
    protected static ?string $model = WhitelistUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Whitelist';

    protected static ?string $recordTitleAttribute = 'username';

    public static function form(Schema $schema): Schema
    {
        return WhitelistUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhitelistUsersTable::configure($table);
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
            'index' => ListWhitelistUsers::route('/'),
            'create' => CreateWhitelistUser::route('/create'),
            'view' => ViewWhitelistUser::route('/{record}'),
        ];
    }
}
