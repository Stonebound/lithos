<?php

declare(strict_types=1);

namespace App\Filament\Resources\OverrideRules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OverrideRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('scope')
                    ->searchable(),
                TextColumn::make('servers.name')
                    ->badge()
                    ->searchable(),
                TextColumn::make('minecraft_version')
                    ->badge(),
                TextColumn::make('path_patterns')
                    ->badge()
                    ->wrap()
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->searchable(),
                IconColumn::make('enabled')
                    ->boolean(),
                TextColumn::make('priority')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('enabled')
                    ->boolean()
                    ->default(true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
