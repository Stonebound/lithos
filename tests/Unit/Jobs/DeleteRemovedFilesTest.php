<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\DeleteRemovedFiles;
use App\Models\Release;
use App\Models\Server;
use App\Services\SftpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use phpseclib3\Net\SFTP;
use Tests\TestCase;

class DeleteRemovedFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_deletes_remote_files_not_present_locally_without_arity_errors(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $disk->put('prepared/config/keep.txt', 'content');

        $server = Server::factory()->create([
            'remote_root_path' => 'remote/path',
            'include_paths' => [],
        ]);

        $release = Release::factory()->create([
            'server_id' => $server->id,
            'prepared_path' => 'prepared',
        ]);

        /** @var SFTP&MockInterface $sftp */
        $sftp = Mockery::mock(SFTP::class);
        $this->expectMock($sftp, 'rawlist')
            ->with('remote/path')
            ->andReturn(['config' => ['type' => 2]]);
        $this->expectMock($sftp, 'rawlist')
            ->with('remote/path/config')
            ->andReturn([
                'keep.txt' => ['type' => 1],
                'delete.txt' => ['type' => 1],
            ]);
        $this->expectMock($sftp, 'is_dir')->with('remote/path/config')->andReturn(true);
        $this->expectMock($sftp, 'delete')->with('remote/path/config/delete.txt', true)->once()->andReturn(true);

        /** @var SftpService&MockInterface $sftpSvc */
        $sftpSvc = Mockery::mock(SftpService::class)->makePartial();
        $sftpSvc->shouldReceive('connect')->with(Mockery::on(fn (Server $s): bool => $s->is($server)))->andReturn($sftp);
        $this->app->instance(SftpService::class, $sftpSvc);

        (new DeleteRemovedFiles($release->id))->handle();

        $this->assertDatabaseHas('release_logs', [
            'release_id' => $release->id,
            'message' => 'Deleted: config/delete.txt',
        ]);
    }
}
