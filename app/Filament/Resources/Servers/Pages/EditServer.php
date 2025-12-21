<?php

declare(strict_types=1);

namespace App\Filament\Resources\Servers\Pages;

use App\Filament\Resources\Servers\ServerResource;
use App\Models\Server;
use App\Services\SftpService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditServer extends EditRecord
{
    protected static string $resource = ServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('Test Connection')
                ->action(function (): void {
                    /** @var Server $server */
                    $server = $this->record;
                    try {
                        /** @var SftpService $sftpSvc */
                        $sftpSvc = app(SftpService::class);
                        $sftp = $sftpSvc->connect($server);
                        unset($sftp);
                        Notification::make()
                            ->title('SFTP connection successful')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('SFTP connection failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('snapshot')
                ->label('Snapshot Remote')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Server $server */
                    $server = $this->record;
                    try {
                        /** @var SftpService $sftpSvc */
                        $sftpSvc = app(SftpService::class);
                        $sftp = $sftpSvc->connect($server);
                        $target = storage_path('app/servers/'.$server->id.'/snapshot');
                        if (! is_dir($target)) {
                            mkdir($target, 0777, true);
                        }
                        $sftpSvc->downloadDirectory($sftp, $server->remote_root_path, $target, $server->include_paths ?? []);
                        Notification::make()
                            ->title('Snapshot complete')
                            ->body('Saved to: '.$target)
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Snapshot failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            DeleteAction::make(),
        ];
    }
}
