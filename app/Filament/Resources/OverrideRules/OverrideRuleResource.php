<?php

declare(strict_types=1);

namespace App\Filament\Resources\OverrideRules;

use App\Filament\Resources\OverrideRules\Pages\CreateOverrideRule;
use App\Filament\Resources\OverrideRules\Pages\EditOverrideRule;
use App\Filament\Resources\OverrideRules\Pages\ListOverrideRules;
use App\Filament\Resources\OverrideRules\Schemas\OverrideRuleForm;
use App\Filament\Resources\OverrideRules\Tables\OverrideRulesTable;
use App\Models\OverrideRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OverrideRuleResource extends Resource
{
    protected static ?string $model = OverrideRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return OverrideRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OverrideRulesTable::configure($table);
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
            'index' => ListOverrideRules::route('/'),
            'create' => CreateOverrideRule::route('/create'),
            'edit' => EditOverrideRule::route('/{record}/edit'),
        ];
    }
}
