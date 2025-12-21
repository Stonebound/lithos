<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Servers\Pages\EditServer;
use App\Models\Server;
use App\Models\User;
use App\Services\SftpService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

        $remoteRel = 'test-server-remote';
        $remoteRoot = Storage::disk('local')->path($remoteRel);
        Storage::disk('local')->makeDirectory($remoteRel);
        Storage::disk('local')->put($remoteRel.'/hello.txt', "hello\n");

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

            public function downloadDirectory(\phpseclib3\Net\SFTP $sftp, string $remotePath, string $localPath, array $includeTopDirs = [], int $depth = 0, array $skipPatterns = []): void
            {
                $root = Storage::disk('local')->path('');
                $remoteRel = ltrim(str_replace($root, '', $remotePath), '/');
                $localRel = 'test-server-local/'.ltrim(str_replace($root, '', $localPath), '/');
                Storage::disk('local')->makeDirectory($localRel);
                foreach (Storage::disk('local')->allFiles($remoteRel) as $file) {
                    $relative = ltrim(str_replace($remoteRel.'/', '', $file), '/');
                    $targetRel = $localRel.'/'.($relative ?: basename($file));
                    $dir = dirname($targetRel);
                    if ($dir !== '.' && ! Storage::disk('local')->exists($dir)) {
                        Storage::disk('local')->makeDirectory($dir);
                    }
                    Storage::disk('local')->put($targetRel, Storage::disk('local')->get($file));
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

        $this->assertTrue(Storage::disk('local')->exists('test-server-local/servers/'.$server->id.'/snapshot/hello.txt'));

        // cleanup
        Storage::disk('local')->deleteDirectory('test-server-local');
        Storage::disk('local')->deleteDirectory($remoteRel);
    }
}
