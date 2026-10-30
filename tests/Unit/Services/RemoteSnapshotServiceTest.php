<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\OverrideRuleType;
use App\Models\OverrideRule;
use App\Models\Server;
use App\Services\RemoteSnapshotService;
use App\Services\SftpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use phpseclib3\Net\SFTP;
use Tests\TestCase;

class RemoteSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    private const string PANEL = 'https://panel.test/api/client/servers/abcd1234';

    private const string DOWNLOAD_URL = 'https://wings.test/download/file?token=signed';

    private string $remoteRoot;

    /**
     * @var list<array{0: string, 1: string}>
     */
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (Process::run('tar --version')->failed()) {
            $this->markTestSkipped('tar binary not available.');
        }

        config(['services.pterodactyl.api_key' => 'test_key', 'services.pterodactyl.base_url' => 'https://panel.test']);
        Storage::fake('local');

        $this->remoteRoot = sys_get_temp_dir().'/remote-snapshot-test-'.bin2hex(random_bytes(6));
        $this->writeRemoteFixture();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->remoteRoot);
        Mockery::close();

        parent::tearDown();
    }

    public function test_archive_snapshot_produces_the_same_tree_as_the_sftp_walk(): void
    {
        $this->createSkipRule(['config/secret*', 'mods/*.disabled']);
        $server = $this->pterodactylServer();

        $this->fakePanel();
        $this->snapshotService()->snapshot($server, 'archive', $this->logger());

        $sftpService = new SftpService;
        $sftpService->downloadDirectory(
            $this->filesystemBackedSftp(),
            '/',
            'sftp',
            SftpService::DEFAULT_SNAPSHOT_TOP_DIRS,
            0,
            ['config/secret*', 'mods/*.disabled'],
        );

        $archiveTree = $this->localTree('archive');
        $this->assertNotEmpty($archiveTree);
        $this->assertSame($this->localTree('sftp'), $archiveTree);
        $this->assertArrayNotHasKey('config/secret.toml', $archiveTree);
        $this->assertArrayNotHasKey('mods/old.jar.disabled', $archiveTree);
        $this->assertArrayNotHasKey('logs/latest.log', $archiveTree);
        $this->assertArrayNotHasKey('mods/linked.jar', $archiveTree);
    }

    public function test_archive_snapshot_only_compresses_existing_included_non_symlink_paths(): void
    {
        $server = $this->pterodactylServer();
        $this->fakePanel();

        $this->snapshotService()->snapshot($server, 'archive', $this->logger());

        Http::assertSent(fn (Request $request): bool => $request->url() === self::PANEL.'/files/compress'
            && $request['root'] === '/'
            && $request['files'] === ['config', 'mods', 'kubejs']);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::PANEL.'/files/delete'
            && $request['files'] === ['archive-2026-10-30T120000Z.tar.gz']);
        $this->assertSame([], Storage::disk('local')->files('tmp'));
    }

    public function test_it_falls_back_to_sftp_when_compression_fails(): void
    {
        $server = $this->pterodactylServer();
        $this->fakePanel(compressStatus: 409);
        $this->expectSftpFallback($server);

        $this->snapshotService()->snapshot($server, 'archive', $this->logger());

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/files/delete'));
        $this->assertLogged('warning', 'Archive snapshot failed, falling back to SFTP');
        $this->assertSame(['mods/fallback.jar' => md5('fallback')], $this->localTree('archive'));
    }

    public function test_it_deletes_the_remote_archive_and_falls_back_when_download_fails(): void
    {
        $server = $this->pterodactylServer();
        $this->fakePanel(downloadStatus: 500);
        $this->expectSftpFallback($server);

        $this->snapshotService()->snapshot($server, 'archive', $this->logger());

        Http::assertSent(fn (Request $request): bool => $request->url() === self::PANEL.'/files/delete'
            && $request['files'] === ['archive-2026-10-30T120000Z.tar.gz']);
        $this->assertLogged('warning', 'falling back to SFTP');
        $this->assertSame([], Storage::disk('local')->files('tmp'));
        $this->assertSame(['mods/fallback.jar' => md5('fallback')], $this->localTree('archive'));
    }

    public function test_it_falls_back_and_discards_partial_output_when_the_archive_is_corrupt(): void
    {
        $server = $this->pterodactylServer();
        $this->fakePanel(archiveBody: substr($this->buildArchive(), 0, 300));
        $this->expectSftpFallback($server);

        $this->snapshotService()->snapshot($server, 'archive', $this->logger());

        $this->assertLogged('warning', 'falling back to SFTP');
        $this->assertSame(['mods/fallback.jar' => md5('fallback')], $this->localTree('archive'));
    }

    public function test_a_failed_remote_cleanup_is_logged_without_failing_the_snapshot(): void
    {
        $server = $this->pterodactylServer();
        $this->fakePanel(deleteStatus: 500);

        $this->snapshotService()->snapshot($server, 'archive', $this->logger());

        $this->assertLogged('warning', 'Could not delete /archive-2026-10-30T120000Z.tar.gz');
        $this->assertArrayHasKey('mods/a.jar', $this->localTree('archive'));
    }

    public function test_non_pterodactyl_servers_use_sftp_only(): void
    {
        Http::fake();
        $server = Server::factory()->create(['username' => 'deployer', 'remote_root_path' => '/']);
        $this->expectSftpFallback($server);

        $this->snapshotService()->snapshot($server, 'archive', $this->logger());

        Http::assertNothingSent();
        $this->assertSame(['mods/fallback.jar' => md5('fallback')], $this->localTree('archive'));
    }

    private function snapshotService(): RemoteSnapshotService
    {
        return app(RemoteSnapshotService::class);
    }

    private function pterodactylServer(): Server
    {
        return Server::factory()->create([
            'username' => 'user.abcd1234',
            'remote_root_path' => '/',
            'include_paths' => [],
        ]);
    }

    /**
     * @param  list<string>  $patterns
     */
    private function createSkipRule(array $patterns): void
    {
        OverrideRule::factory()->create([
            'type' => OverrideRuleType::FileSkip,
            'scope' => 'global',
            'enabled' => true,
            'minecraft_version' => null,
            'path_patterns' => $patterns,
            'payload' => null,
        ]);
    }

    private function fakePanel(int $compressStatus = 200, int $downloadStatus = 200, int $deleteStatus = 204, ?string $archiveBody = null): void
    {
        $listing = array_map(static fn (array $entry): array => ['object' => 'file_object', 'attributes' => $entry], [
            ['name' => 'config', 'is_file' => false, 'is_symlink' => false],
            ['name' => 'mods', 'is_file' => false, 'is_symlink' => false],
            ['name' => 'kubejs', 'is_file' => false, 'is_symlink' => false],
            ['name' => 'logs', 'is_file' => false, 'is_symlink' => false],
            ['name' => 'datapacks', 'is_file' => false, 'is_symlink' => true],
            ['name' => 'server.properties', 'is_file' => true, 'is_symlink' => false],
        ]);

        Http::fake([
            self::PANEL.'/files/list*' => Http::response(['object' => 'list', 'data' => $listing]),
            self::PANEL.'/files/compress' => $compressStatus === 200
                ? Http::response(['object' => 'file_object', 'attributes' => ['name' => 'archive-2026-10-30T120000Z.tar.gz']])
                : Http::response(['errors' => [['detail' => 'not enough disk space']]], $compressStatus),
            self::PANEL.'/files/download*' => Http::response(['object' => 'signed_url', 'attributes' => ['url' => self::DOWNLOAD_URL]]),
            self::DOWNLOAD_URL => Http::response($archiveBody ?? $this->buildArchive(), $downloadStatus),
            self::PANEL.'/files/delete' => Http::response(null, $deleteStatus),
        ]);
    }

    /**
     * Build the archive the way Wings would: every requested top-level path,
     * unfiltered, with symlinks stored as symlinks.
     */
    private function buildArchive(): string
    {
        $archive = $this->remoteRoot.'.tar.gz';
        Process::path($this->remoteRoot)->run(['tar', '-czf', $archive, 'config', 'mods', 'kubejs'])->throw();

        $contents = (string) file_get_contents($archive);
        unlink($archive);

        return $contents;
    }

    private function writeRemoteFixture(): void
    {
        $files = [
            'config/a.toml' => 'a = 1',
            'config/nested/deep/b.json' => '{"b": 2}',
            'config/secret.toml' => 'token = "hidden"',
            'config/'.str_repeat('long', 30).'/c.cfg' => 'long path',
            'mods/a.jar' => random_bytes(20000),
            'mods/old.jar.disabled' => 'disabled',
            'mods/123' => 'numeric name',
            'kubejs/server_scripts/main.js' => 'console.log(1)',
            'logs/latest.log' => 'log',
        ];

        foreach ($files as $path => $content) {
            File::ensureDirectoryExists(dirname($this->remoteRoot.'/'.$path));
            file_put_contents($this->remoteRoot.'/'.$path, $content);
        }

        File::ensureDirectoryExists($this->remoteRoot.'/kubejs/empty');
        symlink('/etc/passwd', $this->remoteRoot.'/mods/linked.jar');
    }

    /**
     * An SFTP mock that serves the on-disk remote fixture.
     *
     * @return SFTP&MockInterface
     */
    private function filesystemBackedSftp(): SFTP
    {
        /** @var SFTP&MockInterface $sftp */
        $sftp = Mockery::mock(SFTP::class);

        $this->expectMock($sftp, 'rawlist')->andReturnUsing(function (string $remotePath): array {
            $local = $this->remoteRoot.'/'.ltrim($remotePath, '/');
            $entries = [];
            foreach (array_diff(scandir($local) ?: [], ['.', '..']) as $name) {
                $path = $local.'/'.$name;
                $entries[$name] = ['type' => is_link($path) ? 3 : (is_dir($path) ? 2 : 1)];
            }

            return $entries;
        });

        $this->expectMock($sftp, 'get')->andReturnUsing(function (string $remote, string $local): bool {
            return copy($this->remoteRoot.'/'.ltrim($remote, '/'), $local);
        });

        return $sftp;
    }

    private function expectSftpFallback(Server $server): void
    {
        $this->mock(SftpService::class, function (MockInterface $mock) use ($server): void {
            $mock->makePartial();
            $connection = Mockery::mock(SFTP::class);
            $this->expectMock($mock, 'connect')->once()->andReturn($connection);
            $this->expectMock($mock, 'downloadDirectory')
                ->once()
                ->withArgs(fn (mixed $sftp, string $root, string $target): bool => $root === $server->remote_root_path && $target === 'archive')
                ->andReturnUsing(function (): void {
                    Storage::disk('local')->put('archive/mods/fallback.jar', 'fallback');
                });
        });
    }

    /**
     * @return callable(string, string): void
     */
    private function logger(): callable
    {
        return function (string $message, string $level): void {
            $this->logs[] = [$level, $message];
        };
    }

    private function assertLogged(string $level, string $needle): void
    {
        foreach ($this->logs as [$loggedLevel, $message]) {
            if ($loggedLevel === $level && str_contains($message, $needle)) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail("No {$level} log containing [{$needle}]. Logs: ".json_encode($this->logs));
    }

    /**
     * @return array<string, string> Relative path => md5 of contents.
     */
    private function localTree(string $dir): array
    {
        $disk = Storage::disk('local');
        $tree = [];
        foreach ($disk->allFiles($dir) as $file) {
            $tree[substr($file, strlen($dir) + 1)] = md5((string) $disk->get($file));
        }
        ksort($tree);

        return $tree;
    }
}
