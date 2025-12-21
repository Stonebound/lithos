<?php

declare(strict_types=1);

namespace App\Filament\Resources\FileChanges\Tables;

use App\Enums\FileChangeType;
use App\Enums\ReleaseStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->badge()
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
                Filter::make('hide_deployed')
                    ->label('Hide Deployed')
                    ->default()
                    ->query(fn (Builder $query) => $query->whereHas('release', fn ($q) => $q->where('status', '!=', ReleaseStatus::Deployed))),
                SelectFilter::make('release_id')
                    ->label('Release')
                    ->relationship('release', 'id')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('change_type')
                    ->label('Change Type')
                    ->options(FileChangeType::class)
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
