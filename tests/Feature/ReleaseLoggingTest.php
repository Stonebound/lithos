<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReleaseStatus;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Livewire\Releases\ReleaseLogs;
use App\Models\Release;
use App\Models\Server;
use App\Models\User;
use App\Services\ModpackImporter;
use App\Services\OverrideApplier;
use App\Services\PterodactylService;
use App\Services\SftpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Mockery;
use Mockery\MockInterface;
use phpseclib3\Net\SFTP;
use Tests\TestCase;

class ReleaseLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_log_messages_to_release(): void
    {
        $server = Server::factory()->create();
        $release = Release::factory()->create(['server_id' => $server->id]);

        ReleaseResource::log($release, 'Test message', 'info');

        $this->assertDatabaseHas('release_logs', [
            'release_id' => $release->id,
            'message' => 'Test message',
            'level' => 'info',
        ]);
    }

    public function test_prepare_release_logs_progress(): void
    {
        Storage::fake('local');
        $server = Server::factory()->create();
        $release = Release::factory()->create([
            'server_id' => $server->id,
            'source_type' => 'zip',
            'source_path' => 'test.zip',
        ]);

        // Mock services to avoid real IO but trigger callbacks
        $this->mock(ModpackImporter::class, function (MockInterface $mock): void {
            $this->expectMock($mock, 'import')->andReturnUsing(function ($r, callable $callback): string {
                $callback('copy', 'file1.txt');

                return 'modpacks/1/new';
            });
        });

        $this->mock(SftpService::class, function (MockInterface $mock): void {
            /** @var SFTP&MockInterface $sftp */
            $sftp = Mockery::mock(SFTP::class);
            $this->expectMock($mock, 'connect')->andReturn($sftp);
            $this->expectMock($mock, 'downloadDirectory');
        });

        $this->mock(OverrideApplier::class, function (MockInterface $mock): void {
            $this->expectMock($mock, 'apply')->andReturnUsing(function ($r, $s, $p, $rem, callable $callback): void {
                $callback('rule', 'Test Rule');
            });
        });

        ReleaseResource::prepareRelease($release);

        $this->assertDatabaseHas('release_logs', ['message' => 'Starting release preparation...']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Importing: file1.txt']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Applying rule: Test Rule']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Release preparation completed successfully.']);
    }

    public function test_deploy_release_logs_progress(): void
    {
        Storage::fake('local');
        $server = Server::factory()->create();
        $release = Release::factory()->create([
            'server_id' => $server->id,
            'prepared_path' => 'modpacks/1/prepared',
            'status' => ReleaseStatus::Prepared,
        ]);

        $this->mock(SftpService::class, function (MockInterface $mock): void {
            /** @var SFTP&MockInterface $sftp */
            $sftp = Mockery::mock(SFTP::class);
            $this->expectMock($mock, 'connect')->andReturn($sftp);
            $this->expectMock($mock, 'syncServerDirectory')->andReturn([
                'failed_workers' => 0,
                'connections' => 1,
                'uploaded_files' => 1,
                'workers' => [
                    [
                        'worker' => 1,
                        'status' => 'success',
                        'uploaded_files' => 1,
                        'first_file' => 'uploaded_file.txt',
                        'last_file' => 'uploaded_file.txt',
                        'failed_file' => null,
                        'error' => null,
                    ],
                ],
            ]);
            $this->expectMock($mock, 'deleteRemoved')->andReturnUsing(function ($s, $l, $r, $inc, $skip, callable $callback): void {
                $callback('delete', 'deleted_file.txt');
            });
        });

        // We don't want to run the actual DeleteRemovedFiles job as it might try to connect
        // But deployRelease calls DeleteRemovedFiles::dispatchSync
        // So we need to mock SftpService again for the job if it runs in same process

        ReleaseResource::deployRelease($release);

        $this->assertDatabaseHas('release_logs', ['message' => 'Starting deployment...']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Worker 1 uploaded 1 files (uploaded_file.txt -> uploaded_file.txt)']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Upload completed with 1 files across 1 connection(s).']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Deployment completed successfully.']);
    }

    public function test_deploy_release_checks_server_status_with_pterodactyl(): void
    {
        Storage::fake('local');
        $server = Server::factory()->create();
        $release = Release::factory()->create([
            'server_id' => $server->id,
            'prepared_path' => 'modpacks/1/prepared',
            'status' => ReleaseStatus::Prepared,
        ]);

        $this->mock(SftpService::class, function (MockInterface $mock): void {
            /** @var SFTP&MockInterface $sftp */
            $sftp = Mockery::mock(SFTP::class);
            $this->expectMock($mock, 'connect')->andReturn($sftp);
            $this->expectMock($mock, 'syncServerDirectory')->andReturn([
                'failed_workers' => 0,
                'connections' => 1,
                'uploaded_files' => 1,
                'workers' => [
                    [
                        'worker' => 1,
                        'status' => 'success',
                        'uploaded_files' => 1,
                        'first_file' => 'uploaded_file.txt',
                        'last_file' => 'uploaded_file.txt',
                        'failed_file' => null,
                        'error' => null,
                    ],
                ],
            ]);
            $this->expectMock($mock, 'deleteRemoved');
        });

        $this->mock(PterodactylService::class, function (MockInterface $mock): void {
            $this->expectMock($mock, 'isPterodactylServer')->andReturn(true);
            $this->expectMock($mock, 'stopServerIfRunning')->andReturn(true);
        });

        ReleaseResource::deployRelease($release);

        $this->assertDatabaseHas('release_logs', ['message' => 'Starting deployment...']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Checking server status...']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Server stopped successfully.']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Worker 1 uploaded 1 files (uploaded_file.txt -> uploaded_file.txt)']);
    }

    public function test_deploy_release_skips_pterodactyl_when_not_configured(): void
    {
        Storage::fake('local');
        $server = Server::factory()->create();
        $release = Release::factory()->create([
            'server_id' => $server->id,
            'prepared_path' => 'modpacks/1/prepared',
            'status' => ReleaseStatus::Prepared,
        ]);

        $this->mock(SftpService::class, function (MockInterface $mock): void {
            /** @var SFTP&MockInterface $sftp */
            $sftp = Mockery::mock(SFTP::class);
            $this->expectMock($mock, 'connect')->andReturn($sftp);
            $this->expectMock($mock, 'syncServerDirectory')->andReturn([
                'failed_workers' => 0,
                'connections' => 1,
                'uploaded_files' => 1,
                'workers' => [
                    [
                        'worker' => 1,
                        'status' => 'success',
                        'uploaded_files' => 1,
                        'first_file' => 'uploaded_file.txt',
                        'last_file' => 'uploaded_file.txt',
                        'failed_file' => null,
                        'error' => null,
                    ],
                ],
            ]);
            $this->expectMock($mock, 'deleteRemoved');
        });

        $this->mock(PterodactylService::class, function (MockInterface $mock): void {
            $this->expectMock($mock, 'isPterodactylServer')->andReturn(false);
            $mock->shouldNotReceive('stopServerIfRunning');
        });

        ReleaseResource::deployRelease($release);

        $this->assertDatabaseHas('release_logs', ['message' => 'Starting deployment...']);
        $this->assertDatabaseMissing('release_logs', ['message' => 'Checking server status...']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Worker 1 uploaded 1 files (uploaded_file.txt -> uploaded_file.txt)']);
    }

    public function test_release_logs_component_shows_logs(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();
        $release = Release::factory()->create(['server_id' => $server->id]);

        $release->logs()->create(['message' => 'Log 1', 'level' => 'info']);
        $release->logs()->create(['message' => 'Log 2', 'level' => 'error']);

        Livewire::actingAs($user);

        /** @var Testable<ReleaseLogs> $component */
        $component = Livewire::test(ReleaseLogs::class, ['release' => $release]);

        $component->assertSee('Log 1');
        $component->assertSee('INFO');
        $component->assertSee('Log 2');
        $component->assertSee('ERROR');
    }
}
