<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Servers\Pages\EditServer;
use App\Models\Server;
use App\Models\User;
use App\Services\SftpService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServersActionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_connection_and_snapshot_actions(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);

        $remoteRoot = storage_path('app/test-server-remote');
        if (! is_dir($remoteRoot)) {
            mkdir($remoteRoot, 0777, true);
        }
        file_put_contents($remoteRoot.'/hello.txt', "hello\n");

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Srv',
            'host' => 'localhost',
            'port' => 22,
            'username' => 'user',
            'auth_type' => 'password',
            'password' => 'pass',
            'remote_root_path' => $remoteRoot,
            'include_paths' => [],
        ]);

        $fakeSftp = new class extends SftpService
        {
            public function connect(Server $server): \phpseclib3\Net\SFTP
            {
                return new \phpseclib3\Net\SFTP('localhost');
            }

            public function downloadDirectory(\phpseclib3\Net\SFTP $sftp, string $remotePath, string $localPath, array $includeTopDirs = [], int $depth = 0): void
            {
                if (! is_dir($localPath)) {
                    mkdir($localPath, 0777, true);
                }
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($remotePath, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    /** @var \SplFileInfo $file */
                    $relative = str_replace($remotePath.'/', '', $file->getPathname());
                    $target = $localPath.'/'.$relative;
                    if ($file->isDir()) {
                        if (! is_dir($target)) {
                            mkdir($target, 0777, true);
                        }
                    } else {
                        if (! is_dir(dirname($target))) {
                            mkdir(dirname($target), 0777, true);
                        }
                        copy($file->getPathname(), $target);
                    }
                }
            }
        };
        $this->app->instance(SftpService::class, $fakeSftp);

        Filament::setCurrentPanel('admin');
        Livewire::test(EditServer::class, ['record' => $server->getKey()])
            ->callAction('testConnection')
            ->assertHasNoActionErrors()
            ->callAction('snapshot')
            ->assertHasNoActionErrors();

        $target = storage_path('app/servers/'.$server->id.'/snapshot');
        $this->assertDirectoryExists($target);
        $this->assertFileExists($target.'/hello.txt');
    }
}
