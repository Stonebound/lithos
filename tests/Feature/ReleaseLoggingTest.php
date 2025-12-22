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
use App\Services\SftpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use phpseclib3\Net\SFTP;
use Tests\TestCase;

class ReleaseLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_log_messages_to_release()
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

    public function test_prepare_release_logs_progress()
    {
        Storage::fake('local');
        $server = Server::factory()->create();
        $release = Release::factory()->create([
            'server_id' => $server->id,
            'source_type' => 'zip',
            'source_path' => 'test.zip',
        ]);

        // Mock services to avoid real IO but trigger callbacks
        $this->mock(ModpackImporter::class, function ($mock) {
            $mock->shouldReceive('import')->andReturnUsing(function ($r, $callback) {
                $callback('copy', 'file1.txt');

                return 'modpacks/1/new';
            });
        });

        $this->mock(SftpService::class, function ($mock) {
            $mock->shouldReceive('connect')->andReturn(Mockery::mock(SFTP::class));
            $mock->shouldReceive('downloadDirectory');
        });

        $this->mock(OverrideApplier::class, function ($mock) {
            $mock->shouldReceive('apply')->andReturnUsing(function ($r, $s, $p, $rem, $callback) {
                $callback('rule', 'Test Rule');
            });
        });

        ReleaseResource::prepareRelease($release);

        $this->assertDatabaseHas('release_logs', ['message' => 'Starting release preparation...']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Importing: file1.txt']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Applying rule: Test Rule']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Release preparation completed successfully.']);
    }

    public function test_deploy_release_logs_progress()
    {
        Storage::fake('local');
        $server = Server::factory()->create();
        $release = Release::factory()->create([
            'server_id' => $server->id,
            'prepared_path' => 'modpacks/1/prepared',
            'status' => ReleaseStatus::Prepared,
        ]);

        $this->mock(SftpService::class, function ($mock) {
            $mock->shouldReceive('connect')->andReturn(Mockery::mock(SFTP::class));
            $mock->shouldReceive('syncDirectory')->andReturnUsing(function ($s, $l, $r, $skip, $callback) {
                $callback('upload', 'uploaded_file.txt');
            });
            $mock->shouldReceive('deleteRemoved')->andReturnUsing(function ($s, $l, $r, $inc, $skip, $callback) {
                $callback('delete', 'deleted_file.txt');
            });
        });

        // We don't want to run the actual DeleteRemovedFiles job as it might try to connect
        // But deployRelease calls DeleteRemovedFiles::dispatchSync
        // So we need to mock SftpService again for the job if it runs in same process

        ReleaseResource::deployRelease($release);

        $this->assertDatabaseHas('release_logs', ['message' => 'Starting deployment...']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Uploaded: uploaded_file.txt']);
        $this->assertDatabaseHas('release_logs', ['message' => 'Deployment completed successfully.']);
    }

    public function test_release_logs_component_shows_logs()
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();
        $release = Release::factory()->create(['server_id' => $server->id]);

        $release->logs()->create(['message' => 'Log 1', 'level' => 'info']);
        $release->logs()->create(['message' => 'Log 2', 'level' => 'error']);

        Livewire::actingAs($user)
            ->test(ReleaseLogs::class, ['release' => $release])
            ->assertSee('Log 1')
            ->assertSee('INFO')
            ->assertSee('Log 2')
            ->assertSee('ERROR');
    }
}
