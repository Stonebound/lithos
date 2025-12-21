<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Models\Server;
use App\Services\Providers\FtbProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FtbProviderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_source_with_real_manifest(): void
    {
        $packId = (string) (env('TEST_FTB_PACK_ID') ?? '');
        $versionId = (string) (env('TEST_FTB_VERSION_ID') ?? '');

        if (! $packId || ! $versionId) {
            $this->markTestSkipped('FTB integration test skipped: missing env TEST_FTB_PACK_ID/TEST_FTB_VERSION_ID');
        }

        $server = Server::query()->create([
            'name' => 'FTB Int',
            'host' => 'localhost',
            'port' => 22,
            'username' => 'user',
            'auth_type' => 'password',
            'password' => 'pass',
            'remote_root_path' => '/',
            'provider' => 'ftb',
            'provider_pack_id' => $packId,
        ]);

        $provider = new FtbProvider;
        $src = $provider->fetchSource($server, $versionId);
        $this->assertSame('dir', $src['type']);
        $this->assertDirectoryExists($src['path']);
    }
}
