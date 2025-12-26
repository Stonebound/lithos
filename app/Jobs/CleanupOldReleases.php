<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReleaseStatus;
use App\Models\Release;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class CleanupOldReleases implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $days = 7) {}

    public function handle(): void
    {
        $cutoff = Carbon::now()->subDays($this->days);

        // Clean up releases older than cutoff
        $releases = Release::query()
            ->whereNotNull('prepared_path')
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($releases as $release) {
            if ($release->status && $release->status === ReleaseStatus::Deployed) {
                $this->cleanupReleaseFiles($release->id);
                $release->delete();
            }
        }

        // Also clean up stray tmp directories under modpacks and servers older than cutoff
        $this->cleanupStoragePrefixes('modpacks', $cutoff);
        $this->cleanupStoragePrefixes('servers', $cutoff);
        $this->cleanupStoragePrefixes('tmp', $cutoff);
        $this->cleanupStoragePrefixes('uploads', $cutoff);
    }

    protected function cleanupReleaseFiles(int $releaseId): void
    {
        $disk = Storage::disk('local');
        $prefix = 'modpacks/'.$releaseId;
        $zipPath = $prefix.'/remote_snapshot.zip';

        if ($disk->exists($zipPath)) {
            try {
                $disk->delete($zipPath);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (! $disk->exists($prefix)) {
            return;
        }

        try {
            $disk->deleteDirectory($prefix);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    protected function cleanupStoragePrefixes(string $base, \Illuminate\Support\Carbon $cutoff): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($base)) {
            return;
        }

        foreach ($disk->allFiles($base) as $f) {
            $disk->delete($f);
        }

        foreach ($disk->directories($base) as $dir) {
            // get timestamp from the files in the directory; if all files are older than cutoff, delete
            $old = true;
            $files = $disk->allFiles($dir);

            if (empty($files)) {
                $old = true;
            } else {
                foreach ($files as $file) {
                    $path = $disk->path($file);
                    if (! file_exists($path)) {
                        continue;
                    }
                    $mtime = Carbon::createFromTimestamp(filemtime($path));
                    if ($mtime->greaterThanOrEqualTo($cutoff)) {
                        $old = false;
                        break;
                    }
                }
            }

            if ($old) {
                try {
                    $disk->deleteDirectory($dir);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }
}
