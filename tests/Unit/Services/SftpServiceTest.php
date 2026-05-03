<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Release;
use App\Models\Server;
use App\Services\SftpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use phpseclib3\Net\SFTP;
use Tests\TestCase;

class SftpServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_delete_removed_files_deletes_remote_files_not_present_locally()
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Local setup: only one file
        $disk->put('sync/config/keep.txt', 'content');

        $sftp = Mockery::mock(SFTP::class);

        // Mock rawlist for top level
        $sftp->shouldReceive('rawlist')
            ->with('remote/path')
            ->andReturn([
                'config' => ['type' => 2],
            ]);

        // Mock rawlist for config
        $sftp->shouldReceive('rawlist')
            ->with('remote/path/config')
            ->andReturn([
                'keep.txt' => ['type' => 1],
                'delete.txt' => ['type' => 1],
            ]);

        $sftp->shouldReceive('is_link')->andReturn(false);

        $sftp->shouldReceive('is_dir')->with('remote/path/config')->andReturn(true);
        $sftp->shouldReceive('is_dir')->with('remote/path/config/keep.txt')->andReturn(false);
        $sftp->shouldReceive('is_dir')->with('remote/path/config/delete.txt')->andReturn(false);

        $sftp->shouldReceive('delete')->with('remote/path/config/delete.txt', true)->once()->andReturn(true);

        $service = new SftpService;

        $sftp->shouldReceive('mkdir')->andReturn(true);
        $sftp->shouldReceive('put')->andReturn(true);

        $service->deleteRemoved($sftp, 'sync', 'remote/path');

        $this->assertTrue(true);
    }

    public function test_delete_removed_files_deletes_remote_directories_recursively()
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Local setup: empty

        $sftp = Mockery::mock(SFTP::class);

        // Mock rawlist for top level
        $sftp->shouldReceive('rawlist')
            ->with('remote/path')
            ->andReturn([
                'config' => ['type' => 2],
            ]);

        $sftp->shouldReceive('is_link')->andReturn(false);
        $sftp->shouldReceive('is_dir')->with('remote/path/config')->andReturn(true);

        // Should delete the directory recursively because it's in default list but not local
        $sftp->shouldReceive('delete')->with('remote/path/config', true)->once()->andReturn(true);

        $service = new SftpService;

        $sftp->shouldReceive('mkdir')->andReturn(true);
        $sftp->shouldReceive('put')->andReturn(true);

        $service->deleteRemoved($sftp, 'sync', 'remote/path');

        $this->assertTrue(true);
    }

    public function test_delete_removed_files_recurses_into_existing_directories()
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Local setup: directory exists
        $disk->makeDirectory('sync/config/existing_dir');
        $disk->put('sync/config/existing_dir/keep.txt', 'content');

        $sftp = Mockery::mock(SFTP::class);

        // Mock rawlist for top level
        $sftp->shouldReceive('rawlist')
            ->with('remote/path')
            ->andReturn([
                'config' => ['type' => 2],
            ]);

        // Mock rawlist for config
        $sftp->shouldReceive('rawlist')
            ->with('remote/path/config')
            ->andReturn([
                'existing_dir' => ['type' => 2],
            ]);

        // Mock rawlist for existing_dir
        $sftp->shouldReceive('rawlist')
            ->with('remote/path/config/existing_dir')
            ->andReturn([
                'keep.txt' => ['type' => 1],
                'delete_me.txt' => ['type' => 1],
            ]);

        $sftp->shouldReceive('is_link')->andReturn(false);
        $sftp->shouldReceive('is_dir')->with('remote/path/config')->andReturn(true);
        $sftp->shouldReceive('is_dir')->with('remote/path/config/existing_dir')->andReturn(true);
        $sftp->shouldReceive('is_dir')->with('remote/path/config/existing_dir/keep.txt')->andReturn(false);
        $sftp->shouldReceive('is_dir')->with('remote/path/config/existing_dir/delete_me.txt')->andReturn(false);

        // Should delete delete_me.txt
        $sftp->shouldReceive('delete')->with('remote/path/config/existing_dir/delete_me.txt', true)->once()->andReturn(true);
        // Should NOT delete keep.txt
        $sftp->shouldReceive('delete')->with('remote/path/config/existing_dir/keep.txt', true)->never();

        $service = new SftpService;

        $sftp->shouldReceive('mkdir')->andReturn(true);
        $sftp->shouldReceive('put')->andReturn(true);

        $service->deleteRemoved($sftp, 'sync', 'remote/path');

        $this->assertTrue(true);
    }

    public function test_delete_removed_method_passes_correct_arguments()
    {
        $sftp = Mockery::mock(SFTP::class);
        $service = new SftpService;

        // Mocking rawlist to return empty so it doesn't do much
        $sftp->shouldReceive('rawlist')->with('remote')->andReturn([]);

        // This should now call deleteRemovedFiles with correct argument order
        $service->deleteRemoved($sftp, 'local', 'remote', ['config'], ['skip_pattern']);

        $this->assertTrue(true);
    }

    public function test_download_directory_recurses_correctly()
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        $sftp = Mockery::mock(SFTP::class);

        // Mock rawlist for top level
        $sftp->shouldReceive('rawlist')
            ->with('remote/path')
            ->andReturn([
                'config' => ['type' => 2],
            ]);

        // Mock rawlist for config
        $sftp->shouldReceive('rawlist')
            ->with('remote/path/config')
            ->andReturn([
                'test.txt' => ['type' => 1],
            ]);

        $sftp->shouldReceive('is_link')->andReturn(false);
        $sftp->shouldReceive('is_dir')->with('remote/path/config')->andReturn(true);
        $sftp->shouldReceive('is_file')->with('remote/path/config')->andReturn(false);
        $sftp->shouldReceive('is_dir')->with('remote/path/config/test.txt')->andReturn(false);
        $sftp->shouldReceive('is_file')->with('remote/path/config/test.txt')->andReturn(true);

        $sftp->shouldReceive('get')
            ->with('remote/path/config/test.txt', Mockery::type('string'))
            ->andReturnUsing(function ($remote, $local) {
                file_put_contents($local, 'content');

                return true;
            });

        $service = new SftpService;
        $service->downloadDirectory($sftp, 'remote/path', 'local/path', ['config']);

        $this->assertTrue($disk->exists('local/path/config/test.txt'));
        $this->assertEquals('content', $disk->get('local/path/config/test.txt'));
    }

    public function test_download_directory_defaults_include_mods()
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        $sftp = Mockery::mock(SFTP::class);

        // Mock rawlist for top level
        $sftp->shouldReceive('rawlist')
            ->with('remote/path')
            ->andReturn([
                'mods' => ['type' => 2],
            ]);

        // Mock rawlist for mods
        $sftp->shouldReceive('rawlist')
            ->with('remote/path/mods')
            ->andReturn([
                'test.jar' => ['type' => 1],
            ]);

        $sftp->shouldReceive('is_link')->andReturn(false);
        $sftp->shouldReceive('is_dir')->with('remote/path/mods')->andReturn(true);
        $sftp->shouldReceive('is_file')->with('remote/path/mods')->andReturn(false);
        $sftp->shouldReceive('is_dir')->with('remote/path/mods/test.jar')->andReturn(false);
        $sftp->shouldReceive('is_file')->with('remote/path/mods/test.jar')->andReturn(true);

        $sftp->shouldReceive('get')
            ->with('remote/path/mods/test.jar', Mockery::type('string'))
            ->andReturnUsing(function ($remote, $local) {
                file_put_contents($local, 'jar content');

                return true;
            });

        $service = new SftpService;
        // No includeTopDirs passed, should use defaults which include 'mods'
        $service->downloadDirectory($sftp, 'remote/path', 'local/path');

        $this->assertTrue($disk->exists('local/path/mods/test.jar'));
    }

    public function test_delete_removed_files_respects_nested_skip_patterns()
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Make sure config directory exists locally
        $disk->makeDirectory('sync/config');

        $sftp = Mockery::mock(SFTP::class);

        // Mock rawlist for top level
        $sftp->shouldReceive('rawlist')
            ->with('remote/path')
            ->andReturn([
                'config' => ['type' => 2],
            ]);

        // Mock rawlist for config
        $sftp->shouldReceive('rawlist')
            ->with('remote/path/config')
            ->andReturn([
                'test.log' => ['type' => 1],
            ]);

        $sftp->shouldReceive('is_link')->andReturn(false);
        $sftp->shouldReceive('is_dir')->with('remote/path/config')->andReturn(true);
        $sftp->shouldReceive('is_dir')->with('remote/path/config/test.log')->andReturn(false);

        // Should NOT delete config/test.log because of skip pattern
        $sftp->shouldReceive('delete')->with('remote/path/config/test.log', true)->never();

        $service = new SftpService;

        $sftp->shouldReceive('mkdir')->andReturn(true);
        $sftp->shouldReceive('put')->andReturn(true);

        $service->syncDirectory($sftp, $disk->path('sync'), 'remote/path', ['config/*.log']);

        $this->assertTrue(true);
    }

    public function test_delete_removed_files_defaults_to_specific_folders()
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Make config exist locally so it recurses instead of deleting the whole folder
        $disk->makeDirectory('sync/config');

        $sftp = Mockery::mock(SFTP::class);

        // Mock rawlist for top level
        $sftp->shouldReceive('rawlist')
            ->with('remote/path')
            ->andReturn([
                'config' => ['type' => 2],
                'mods' => ['type' => 2],
                'other' => ['type' => 1],
            ]);

        $sftp->shouldReceive('is_link')->andReturn(false);

        // 'config' is in default list, so it should be processed
        $sftp->shouldReceive('is_dir')->with('remote/path/config')->andReturn(true);
        // Mock rawlist for config
        $sftp->shouldReceive('rawlist')->with('remote/path/config')->andReturn([]);

        // 'mods' IS in default list for delete, so it should be processed
        $sftp->shouldReceive('is_dir')->with('remote/path/mods')->andReturn(true);
        // It doesn't exist locally, so it should be deleted
        $sftp->shouldReceive('delete')->with('remote/path/mods', true)->once()->andReturn(true);

        // 'other' is NOT in default list, so it should be skipped
        $sftp->shouldReceive('delete')->with('remote/path/other', true)->never();

        $service = new SftpService;

        // syncDirectory calls deleteRemovedFiles without includeTopDirs
        $sftp->shouldReceive('mkdir')->andReturn(true);
        $sftp->shouldReceive('put')->andReturn(true);

        $service->deleteRemoved($sftp, 'sync', 'remote/path');

        $this->assertTrue(true);
    }

    public function test_it_handles_numeric_filenames_as_strings()
    {
        Storage::fake('local');
        $sftp = Mockery::mock(SFTP::class);

        // Mock rawlist with a numeric key (PHP will cast this to int if we aren't careful)
        $sftp->shouldReceive('rawlist')
            ->with('remote/path')
            ->andReturn([
                123 => ['type' => 1], // Numeric filename "123"
            ]);

        $sftp->shouldReceive('get')->andReturn(true);

        $service = new SftpService;

        // This should not throw a TypeError
        $service->downloadDirectory($sftp, 'remote/path', 'local/path', ['123']);

        $this->assertTrue(true);
    }

    public function test_sync_directory_can_upload_a_selected_subset_of_files()
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        $disk->put('sync/config/keep.txt', 'keep');
        $disk->put('sync/config/skip.txt', 'skip');
        $disk->put('sync/mods/mod.jar', 'jar');

        $sftp = Mockery::mock(SFTP::class);
        $sftp->shouldReceive('is_dir')->with('remote/path/config')->once()->andReturn(false);
        $sftp->shouldReceive('mkdir')->with('remote/path/config', -1, true)->once()->andReturn(true);
        $sftp->shouldReceive('is_dir')->with('remote/path/mods')->once()->andReturn(true);

        $sftp->shouldReceive('put')->with('remote/path/config/keep.txt', Mockery::type('string'), SFTP::SOURCE_LOCAL_FILE)->once()->andReturn(true);
        $sftp->shouldReceive('put')->with('remote/path/mods/mod.jar', Mockery::type('string'), SFTP::SOURCE_LOCAL_FILE)->once()->andReturn(true);
        $sftp->shouldReceive('put')->with('remote/path/config/skip.txt', Mockery::type('string'), SFTP::SOURCE_LOCAL_FILE)->never();

        $service = new SftpService;

        $uploadedFiles = $service->syncDirectory(
            $sftp,
            $disk->path('sync'),
            'remote/path',
            [],
            null,
            ['config/keep.txt', 'mods/mod.jar'],
        );

        $this->assertSame(2, $uploadedFiles);
    }

    public function test_sync_server_directory_uses_configured_parallel_connections()
    {
        Storage::fake('local');
        Storage::disk('local')->put('sync/alpha.txt', 'alpha');
        Storage::disk('local')->put('sync/beta.txt', 'beta');
        Storage::disk('local')->put('sync/gamma.txt', 'gamma');

        config()->set('services.sftp.parallel_upload_connections', 2);
        config()->set('services.sftp.parallel_upload_driver', 'process');

        Concurrency::shouldReceive('driver')->once()->with('process')->andReturnSelf();
        Concurrency::shouldReceive('run')
            ->once()
            ->with(Mockery::on(fn (array $tasks): bool => count($tasks) === 2))
            ->andReturn([
                ['worker' => 2, 'status' => 'success', 'uploaded_files' => 1, 'first_file' => 'gamma.txt', 'last_file' => 'gamma.txt', 'failed_file' => null, 'error' => null],
                ['worker' => 1, 'status' => 'success', 'uploaded_files' => 2, 'first_file' => 'alpha.txt', 'last_file' => 'beta.txt', 'failed_file' => null, 'error' => null],
            ]);

        $service = new SftpService;
        $server = Server::factory()->make([
            'auth_type' => 'password',
            'password' => 'secret',
        ]);

        $summary = $service->syncServerDirectory($server, 'sync', 'remote/path');

        $this->assertSame(2, $summary['connections']);
        $this->assertSame(0, $summary['failed_workers']);
        $this->assertSame(3, $summary['uploaded_files']);
        $this->assertSame(1, $summary['workers'][0]['worker']);
        $this->assertSame(2, $summary['workers'][1]['worker']);
    }

    public function test_sync_server_directory_passes_release_id_to_worker_uploads_for_process_driver()
    {
        Storage::fake('local');
        Storage::disk('local')->put('sync/alpha.txt', 'alpha');
        Storage::disk('local')->put('sync/beta.txt', 'beta');

        config()->set('services.sftp.parallel_upload_connections', 2);
        config()->set('services.sftp.parallel_upload_driver', 'process');

        /** @var SftpService&MockInterface $service */
        $service = Mockery::mock(SftpService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $this->app->instance(SftpService::class, $service);

        $service->shouldReceive('uploadRelativeFileBatch')
            ->twice()
            ->with(
                Mockery::type('array'),
                'sync',
                'remote/path',
                Mockery::type('array'),
                Mockery::type('int'),
                123,
            )
            ->andReturnUsing(fn (array $serverConfig, string $localPath, string $remotePath, array $relativeFiles, int $worker, ?int $releaseId): array => [
                'worker' => $worker,
                'status' => 'success',
                'uploaded_files' => count($relativeFiles),
                'first_file' => $relativeFiles[0] ?? null,
                'last_file' => $relativeFiles[array_key_last($relativeFiles)] ?? null,
                'failed_file' => null,
                'error' => null,
            ]);

        Concurrency::shouldReceive('driver')->once()->with('process')->andReturnSelf();
        Concurrency::shouldReceive('run')
            ->once()
            ->andReturnUsing(fn (array $tasks): array => array_map(fn (callable $task): array => $task(), $tasks));

        $server = Server::factory()->make([
            'auth_type' => 'password',
            'password' => 'secret',
        ]);

        $summary = $service->syncServerDirectory($server, 'sync', 'remote/path', [], 123);

        $this->assertSame(0, $summary['failed_workers']);
        $this->assertSame(2, $summary['uploaded_files']);
        $this->assertSame(2, $summary['connections']);
    }

    public function test_upload_relative_file_batch_returns_failure_details()
    {
        /** @var SftpService&MockInterface $service */
        $service = Mockery::mock(SftpService::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('connectFromConfig')
            ->once()
            ->andThrow(new \RuntimeException('Failed to upload: remote/path/config/broken.txt'));

        $summary = $service->uploadRelativeFileBatch(
            [
                'host' => '127.0.0.1',
                'port' => 22,
                'username' => 'root',
                'auth_type' => 'password',
                'password' => 'secret',
                'private_key_path' => null,
            ],
            'sync',
            'remote/path',
            ['config/broken.txt', 'config/other.txt'],
            3,
            null,
        );

        $this->assertSame('failed', $summary['status']);
        $this->assertSame(3, $summary['worker']);
        $this->assertSame('broken.txt', $summary['failed_file']);
        $this->assertSame('Failed to upload: remote/path/config/broken.txt', $summary['error']);
    }

    public function test_upload_relative_file_batch_logs_throttled_progress()
    {
        config()->set('services.sftp.progress_every_files', 2);
        config()->set('services.sftp.progress_every_seconds', 9999.0);

        $release = Release::factory()->create();

        $sftp = Mockery::mock(SFTP::class);
        /** @var SftpService&MockInterface $service */
        $service = Mockery::mock(SftpService::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('connectFromConfig')
            ->once()
            ->andReturn($sftp);

        $service->shouldReceive('syncDirectory')
            ->once()
            ->with($sftp, 'sync', 'remote/path', [], Mockery::type('callable'), ['alpha.txt', 'beta.txt', 'gamma.txt'])
            ->andReturnUsing(function ($sftp, $localPath, $remotePath, $skipPatterns, $onProgress, $relativeFiles) {
                $onProgress('upload', 'alpha.txt');
                $onProgress('upload', 'beta.txt');
                $onProgress('upload', 'gamma.txt');

                return 3;
            });

        $summary = $service->uploadRelativeFileBatch(
            [
                'host' => '127.0.0.1',
                'port' => 22,
                'username' => 'root',
                'auth_type' => 'password',
                'password' => 'secret',
                'private_key_path' => null,
            ],
            'sync',
            'remote/path',
            ['alpha.txt', 'beta.txt', 'gamma.txt'],
            1,
            $release->id,
        );

        $this->assertSame('success', $summary['status']);
        $this->assertDatabaseCount('release_logs', 2);
        $this->assertDatabaseHas('release_logs', [
            'release_id' => $release->id,
            'message' => 'Worker 1 progress: 2/3 files (66.7%), latest: beta.txt',
        ]);
        $this->assertDatabaseHas('release_logs', [
            'release_id' => $release->id,
            'message' => 'Worker 1 progress: 3/3 files (100.0%), latest: gamma.txt',
        ]);
    }
}
