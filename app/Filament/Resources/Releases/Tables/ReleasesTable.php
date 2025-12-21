<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Tables;

use App\Enums\ReleaseStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
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
                TextColumn::make('status')
                    ->badge()
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
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->visible(fn ($record): bool => in_array($record->status, [ReleaseStatus::Draft, ReleaseStatus::Prepared]))
                    ->action(function ($record): void {
                        $providerVersionId = $record->provider_version_id ?? null;

                        \App\Jobs\PrepareRelease::dispatch(
                            $record->id,
                            $providerVersionId ? (string) $providerVersionId : null,
                            Auth::id()
                        );

                        Notification::make()
                            ->title('Preparation queued')
                            ->body('The release preparation has been started in the background.')
                            ->info()
                            ->send();
                    }),
                Action::make('deploy')
                    ->label('Deploy')
                    ->icon(Heroicon::OutlinedRocketLaunch)
                    ->visible(fn ($record): bool => $record->status === ReleaseStatus::Prepared)
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        \App\Jobs\DeployRelease::dispatch($record->id, Auth::id());

                        Notification::make()
                            ->title('Deployment queued')
                            ->body('The deployment has been started in the background.')
                            ->info()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
