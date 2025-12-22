<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Actions;

use App\Jobs\PrepareBackupZip;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DownloadBackupZipAction
{
    public static function make(): Action
    {
        return Action::make('download-backup-zip')
            ->label('Backup')
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(function ($record) {
                // Only show if backup folder exists and not preparing
                $backupPath = "modpacks/{$record->id}/remote";
                $zipPath = "modpacks/{$record->id}/remote_snapshot.zip";
                $disk = Storage::disk('local');

                return ! $record->isPreparing() && ($disk->exists($backupPath) || $disk->exists($zipPath));
            })
            ->disabled(fn ($record) => $record->isPreparing())
            ->requiresConfirmation()
            ->modalHeading('Prepare Backup Zip')
            ->modalDescription('This will prepare a zip file of the remote snapshot for download. You will be notified when it is ready.')
            ->action(function ($record) {
                $zipPath = "modpacks/{$record->id}/remote_snapshot.zip";
                $disk = Storage::disk('local');
                if (! $disk->exists($zipPath)) {
                    PrepareBackupZip::dispatch($record->id, Auth::id());
                    Notification::make()
                        ->title('Backup zip is being prepared')
                        ->body('You will be notified when the backup zip is ready for download.')
                        ->info()
                        ->send();
                } else {
                    // Provide download link (handled by a route/controller)
                    $url = route('releases.download-backup-zip', $record->id);
                    Notification::make()
                        ->title('Backup zip ready')
                        ->body('Click to download the backup zip.')
                        ->success()
                        ->actions([
                            Action::make('download')
                                ->label('Download')
                                ->url($url)
                                ->openUrlInNewTab(),
                        ])
                        ->send();
                }
            });
    }
}
