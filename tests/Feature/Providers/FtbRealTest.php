<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Models\Server;
use App\Services\Providers\FtbProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FtbRealTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_ftb_pack_fetches_directory(): void
    {
        if (! filter_var(getenv('RUN_EXTERNAL_TESTS') ?: '', FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('External tests disabled. Set RUN_EXTERNAL_TESTS=true to run.');
        }

        // Provided by user
        $packId = 99;

        $server = Server::query()->create([
            'name' => 'FTB Real',
            'host' => 'localhost',
            'port' => 22,
            'username' => 'user',
            'auth_type' => 'password',
            'password' => 'pass',
            'remote_root_path' => '/',
            'provider' => 'ftb',
            'provider_pack_id' => (string) $packId,
        ]);

        $provider = new FtbProvider;
        $versions = $provider->listVersions((string) $packId);
        $this->assertNotEmpty($versions, 'No versions returned for FTB pack');

        /** @var array{id: int|string, name: string} $version */
        $version = $versions[0];

        $versionId = $version['id'];
        $src = $provider->fetchSource((string) $packId, $versionId);
        $this->assertSame('dir', $src['type']);
        $this->assertDirectoryExists($src['path']);
    }
}
