<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Releases\Pages\EditRelease;
use App\Models\Release;
use App\Models\Server;
use App\Models\User;
use App\Services\Providers\ProviderInterface;
use App\Services\Providers\ProviderResolver;
use App\Services\SftpService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReleasesActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    #[Test]
    public function prepare_and_deploy_actions_work(): void
    {
        // Create a maintainer user
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'maintainer',
        ]);
        $this->actingAs($user);

        // Create a server and a fake remote root
        $remoteRoot = storage_path('app/test-remote');
        if (! is_dir($remoteRoot)) {
            mkdir($remoteRoot, 0777, true);
        }
        file_put_contents($remoteRoot.'/foo.txt', "remote\n");

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Test Server',
            'host' => 'localhost',
            'port' => 22,
            'username' => 'user',
            'auth_type' => 'password',
            'password' => 'pass',
            'remote_root_path' => $remoteRoot,
            'include_paths' => [],
            'provider' => 'ftb',
            'provider_pack_id' => '123',
            'provider_current_version' => null,
        ]);

        // Fake provider resolver to return a simple provider
        $sourceDir = storage_path('app/test-source');
        if (! is_dir($sourceDir)) {
            mkdir($sourceDir, 0777, true);
        }
        file_put_contents($sourceDir.'/foo.txt', "prepared\n");
        file_put_contents($sourceDir.'/bar.txt', "new\n");

        // Create a release
        /** @var Release $release */
        $release = Release::query()->create([
            'server_id' => $server->id,
            'status' => 'draft',
            // Provide initial source to satisfy NOT NULL constraints; will be replaced by provider.
            'source_type' => 'dir',
            'source_path' => $sourceDir,
        ]);

        $fakeProvider = new class implements ProviderInterface
        {
            public function listVersions(string|int $providerPackId): array
            {
                return [
                    ['id' => 'v1', 'name' => 'Version 1'],
                ];
            }

            public function fetchSource($providerPackId, $versionId): array
            {
                return ['type' => 'directory', 'path' => storage_path('app/test-source')];
            }
        };

        $fakeResolver = new class($fakeProvider) extends ProviderResolver
        {
            public function __construct(private ProviderInterface $provider) {}

            public function for(Server $server): ?ProviderInterface
            {
                return $this->provider;
            }
        };
        $this->app->instance(ProviderResolver::class, $fakeResolver);

        // Fake SFTP service to copy remote to local and no-op deploy
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

            public function syncDirectory(\phpseclib3\Net\SFTP $sftp, string $localPath, string $remotePath, bool $deleteRemoved = false, array $includeTopDirs = []): void
            {
                // no-op for test
            }
        };
        $this->app->instance(SftpService::class, $fakeSftp);

        // Use Livewire to call the actions on the Edit page
        Filament::setCurrentPanel('admin');
        Livewire::test(EditRelease::class, ['record' => $release->getKey()])
            ->set('data.provider_version_id', 'v1')
            ->callAction('prepare')
            ->assertHasNoActionErrors()
            ->callAction('deploy', data: ['delete_removed' => true])
            ->assertHasNoActionErrors();

        // Assert release state updated
        $release = $release->refresh();
        $this->assertSame('deployed', $release->status);
        $this->assertNotEmpty($release->prepared_path);
        $this->assertDirectoryExists($release->prepared_path);

        // One file modified, one new
        $this->assertDatabaseHas('file_changes', [
            'release_id' => $release->id,
            'relative_path' => 'foo.txt',
            'change_type' => 'modified',
        ]);
        $this->assertDatabaseHas('file_changes', [
            'release_id' => $release->id,
            'relative_path' => 'bar.txt',
            'change_type' => 'added',
        ]);
    }
}
