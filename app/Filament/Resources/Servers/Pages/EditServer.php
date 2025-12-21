<?php

declare(strict_types=1);

namespace App\Filament\Resources\Servers\Pages;

use App\Filament\Resources\Servers\ServerResource;
use App\Jobs\SnapshotServer;
use App\Models\Server;
use App\Services\SftpService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

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
                    $userId = Auth::id();
                    SnapshotServer::dispatch($server->id, $userId);
                    Notification::make()
                        ->title('Snapshot queued')
                        ->body('The remote snapshot will run in the background.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
