<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReleaseStatus;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\Release;
use App\Models\Server;
use App\Services\SftpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReleaseDeployEmptyPreparedGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_deploy_aborts_and_does_not_clean_up_remote_files_when_prepared_directory_is_empty(): void
    {
        Storage::fake('local');

        $server = Server::factory()->create([
            'auth_type' => 'password',
            'password' => 'pw',
            'remote_root_path' => '/srv/mc',
            'include_paths' => [],
        ]);

        $release = Release::factory()->create([
            'server_id' => $server->id,
            'status' => ReleaseStatus::Prepared,
            'prepared_path' => 'modpacks/1/prepared',
        ]);

        // Prepared directory was wiped (e.g. by an unrelated cleanup bug) before deploy ran.
        $fake = new class extends SftpService
        {
            public function syncServerDirectory(Server $server, string $localPath, string $remotePath, array $skipPatterns = [], ?int $releaseId = null): array
            {
                return [
                    'failed_workers' => 0,
                    'connections' => 0,
                    'uploaded_files' => 0,
                    'workers' => [],
                ];
            }

            public function deleteRemoved($sftp, string $localPath, string $remotePath, array $includeTopDirs = [], array $skipPatterns = [], ?callable $onProgress = null): void
            {
                throw new \RuntimeException('deleteRemoved must not be called when nothing was uploaded.');
            }
        };
        $this->app->instance(SftpService::class, $fake);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Prepared directory contains no files.');

        try {
            ReleaseResource::deployRelease($release);
        } finally {
            $release->refresh();
            $this->assertSame(ReleaseStatus::Prepared, $release->status);
        }
    }
}
