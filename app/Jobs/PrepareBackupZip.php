<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Release;
use App\Models\User;
use App\Services\AuditService;
use App\Services\FileUtility;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PrepareBackupZip implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $releaseId,
        public ?int $userId = null
    ) {}

    public function handle(): void
    {
        $release = Release::find($this->releaseId);
        if (! $release) {
            return;
        }

        $auditService = app(AuditService::class);
        $auditService->log('create_zip', $release);

        $disk = Storage::disk('local');
        $backupDir = "modpacks/{$release->id}/remote";
        $zipPath = "modpacks/{$release->id}/remote_snapshot.zip";

        if (! $disk->exists($backupDir)) {
            return;
        }

        if (! FileUtility::hasSufficientDiskspace()) {
            return;
        }

        // Remove old zip if exists
        if ($disk->exists($zipPath)) {
            $disk->delete($zipPath);
        }

        // Create zip archive
        $zip = new \ZipArchive;
        $fullZipPath = $disk->path($zipPath);
        if ($zip->open($fullZipPath, \ZipArchive::CREATE) !== true) {
            return;
        }

        $files = $disk->allFiles($backupDir);
        foreach ($files as $file) {
            $localPath = $disk->path($file);
            $relativePath = substr($file, strlen($backupDir) + 1);
            $zip->addFile($localPath, $relativePath);
        }
        $zip->close();

        if ($this->userId) {
            $user = User::find($this->userId);
            if ($user) {
                $url = route('releases.download-backup-zip', $release->id);
                Notification::make()
                    ->title('Backup zip ready')
                    ->body('The backup zip is ready for download.')
                    ->success()
                    ->actions([
                        Action::make('download')
                            ->label('Download')
                            ->url($url)
                            ->openUrlInNewTab(),
                    ])
                    ->sendToDatabase($user);
            }
        }
    }

    public function uniqueId(): string
    {
        return 'release:'.$this->releaseId.':prepare-backup-zip';
    }
}
