<?php

declare(strict_types=1);

namespace App\Filament\Resources\FileChanges\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FileChangesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('release.id')
                    ->searchable(),
                TextColumn::make('relative_path')
                    ->searchable(),
                TextColumn::make('change_type')
                    ->searchable(),
                IconColumn::make('is_binary')
                    ->boolean(),
                TextColumn::make('checksum_old')
                    ->searchable(),
                TextColumn::make('checksum_new')
                    ->searchable(),
                TextColumn::make('size_old')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('size_new')
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
                SelectFilter::make('release_id')
                    ->label('Release')
                    ->relationship('release', 'id')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('change_type')
                    ->label('Change Type')
                    ->options([
                        'added' => 'Added',
                        'modified' => 'Modified',
                        'removed' => 'Removed',
                    ])
                    ->multiple()
                    ->preload(),
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
