<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\FileChangeType;
use App\Enums\ReleaseStatus;
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
use phpseclib3\Net\SFTP;
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
        $remoteRel = 'test-remote';
        $remoteRoot = Storage::disk('local')->path($remoteRel);
        Storage::disk('local')->makeDirectory($remoteRel);
        Storage::disk('local')->put($remoteRel.'/foo.txt', "remote\n");

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
        $sourceRel = 'test-source';
        $sourceDir = Storage::disk('local')->path($sourceRel);
        Storage::disk('local')->makeDirectory($sourceRel);
        Storage::disk('local')->put($sourceRel.'/foo.txt', "prepared\n");
        Storage::disk('local')->put($sourceRel.'/bar.txt', "new\n");

        // Create a release
        /** @var Release $release */
        $release = Release::query()->create([
            'server_id' => $server->id,
            'status' => ReleaseStatus::Draft,
            // Provide initial source to satisfy NOT NULL constraints; will be replaced by provider.
            'source_type' => 'dir',
            'source_path' => $sourceDir,
            'version_label' => 'v1',
            'provider_version_id' => 'v1',
        ]);

        $fakeProvider = new class implements ProviderInterface
        {
            public function listVersions(string|int $providerPackId): array
            {
                return [
                    ['id' => 'v1', 'name' => 'Version 1'],
                ];
            }

            public function fetchSource(string|int $providerPackId, string|int $versionId): array
            {
                return ['type' => 'dir', 'path' => Storage::disk('local')->path('test-source')];
            }
        };

        $fakeResolver = new class($fakeProvider) extends ProviderResolver
        {
            public function __construct(private ProviderInterface $provider) {}

            public function for(Server $server): ProviderInterface
            {
                return $this->provider;
            }
        };
        $this->app->instance(ProviderResolver::class, $fakeResolver);

        // Fake SFTP service to copy remote to local and no-op deploy
        $fakeSftp = new class extends SftpService
        {
            public function connect(Server $server): SFTP
            {
                return new SFTP('localhost');
            }

            public function downloadDirectory(SFTP $sftp, string $remotePath, string $localPath, array $includeTopDirs = [], int $depth = 0, array $skipPatterns = [], string $accumulatedPath = ''): void
            {
                $root = Storage::disk('local')->path('');
                $remoteRel = ltrim(str_replace($root, '', $remotePath), '/');
                $localRel = ltrim(str_replace($root, '', $localPath), '/');
                Storage::disk('local')->makeDirectory($localRel);
                foreach (Storage::disk('local')->allFiles($remoteRel) as $file) {
                    if (! is_string($file)) {
                        continue;
                    }

                    $relative = ltrim(str_replace($remoteRel.'/', '', $file), '/');

                    // Simple skip check for test mock
                    $skipped = false;
                    foreach ($skipPatterns as $p) {
                        if (fnmatch($p, $relative)) {
                            $skipped = true;
                            break;
                        }
                    }
                    if ($skipped) {
                        continue;
                    }

                    $targetRel = $localRel.'/'.($relative ?: basename($file));
                    $dir = dirname($targetRel);
                    if ($dir !== '.' && ! Storage::disk('local')->exists($dir)) {
                        Storage::disk('local')->makeDirectory($dir);
                    }

                    $contents = Storage::disk('local')->get($file);

                    if (! is_string($contents)) {
                        throw new \RuntimeException('Unexpected storage contents.');
                    }

                    Storage::disk('local')->put($targetRel, $contents);
                }
            }

            public function syncDirectory(SFTP $sftp, string $localPath, string $remotePath, array $skipPatterns = [], ?callable $onProgress = null, ?array $relativeFiles = null): int
            {
                return count($relativeFiles ?? []);
            }

            public function syncServerDirectory(Server $server, string $localPath, string $remotePath, array $skipPatterns = [], ?int $releaseId = null): array
            {
                return [
                    'failed_workers' => 0,
                    'connections' => 1,
                    'uploaded_files' => 0,
                    'workers' => [],
                ];
            }
        };
        $this->app->instance(SftpService::class, $fakeSftp);

        // Use Livewire to call the actions on the Edit page
        Filament::setCurrentPanel('admin');
        Livewire::test(EditRelease::class, ['record' => $release->getKey()])
            ->callAction('prepare')
            ->assertHasNoActionErrors();

        $release = $release->refresh();

        Livewire::test(EditRelease::class, ['record' => $release->getKey()])
            ->callAction('deploy', ['confirmation' => 'DEPLOY NOW'])
            ->assertHasNoActionErrors();

        // Assert release state updated
        $release = $release->refresh();
        $this->assertSame(ReleaseStatus::Deployed, $release->status);
        $this->assertNotEmpty($release->prepared_path);
        $this->assertIsString($release->prepared_path);
        $this->assertTrue(Storage::disk('local')->exists($release->prepared_path));

        // Assert server state updated
        $server = $server->refresh();
        $this->assertSame('v1', $server->provider_current_version);

        // One file modified, one new
        $this->assertDatabaseHas('file_changes', [
            'release_id' => $release->id,
            'relative_path' => 'foo.txt',
            'change_type' => FileChangeType::Modified->value,
        ]);
        $this->assertDatabaseHas('file_changes', [
            'release_id' => $release->id,
            'relative_path' => 'bar.txt',
            'change_type' => FileChangeType::Added->value,
        ]);
    }
}
