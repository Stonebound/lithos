<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SftpService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use phpseclib3\Net\SFTP;
use Tests\TestCase;

class SftpServiceTest extends TestCase
{
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
}
