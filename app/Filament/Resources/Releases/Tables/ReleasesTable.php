<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Tables;

use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\Release;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ReleasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('server.name')
                    ->searchable(),
                TextColumn::make('version_label')
                    ->searchable(),
                TextColumn::make('source_type')
                    ->searchable(),
                TextColumn::make('source_path')
                    ->searchable(),
                TextColumn::make('extracted_path')
                    ->searchable(),
                TextColumn::make('remote_snapshot_path')
                    ->searchable(),
                TextColumn::make('prepared_path')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
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
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('prepare')
                    ->label('Prepare')
                    ->action(function (Release $record): void {
                        try {
                            ReleaseResource::prepareRelease($record, null);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Prepare failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }
                    }),
                Action::make('deploy')
                    ->label('Deploy')
                    ->visible(fn (): bool => (function () {
                        $u = Auth::user();

                        return $u ? in_array($u->role ?? 'viewer', ['maintainer', 'admin'], true) : false;
                    })())
                    ->action(function (Release $record): void {
                        try {
                            ReleaseResource::deployRelease($record, false);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Deploy failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
