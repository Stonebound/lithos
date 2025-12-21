<?php

declare(strict_types=1);

namespace App\Filament\Resources\Servers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('host')
                    ->searchable(),
                TextColumn::make('port')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('username')
                    ->searchable(),
                TextColumn::make('auth_type')
                    ->searchable(),
                TextColumn::make('private_key_path')
                    ->searchable(),
                TextColumn::make('remote_root_path')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('provider')
                    ->searchable(),
                TextColumn::make('provider_pack_id')
                    ->searchable(),
                TextColumn::make('provider_current_version')
                    ->searchable(),
            ])
            ->filters([
                //
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
