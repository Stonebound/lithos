<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::get('/releases/{release}/download-backup-zip', function ($releaseId) {
    $zipPath = "modpacks/{$releaseId}/remote_snapshot.zip";
    $disk = Storage::disk('local');
    if (! $disk->exists($zipPath)) {
        abort(404);
    }
    $fullPath = $disk->path($zipPath);

    return new StreamedResponse(function () use ($fullPath) {
        $stream = fopen($fullPath, 'rb');
        fpassthru($stream);
        fclose($stream);
    }, 200, [
        'Content-Type' => 'application/zip',
        'Content-Disposition' => 'attachment; filename="remote_snapshot.zip"',
    ]);
})->name('releases.download-backup-zip');
