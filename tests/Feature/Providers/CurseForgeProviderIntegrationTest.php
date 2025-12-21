<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Models\Server;
use App\Services\Providers\CurseForgeProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurseForgeProviderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_versions_and_fetch_source_with_real_api(): void
    {
        $apiKey = config('services.curseforge.key');
        $projectId = (string) (env('TEST_CURSEFORGE_PROJECT_ID') ?? '');
        $fileId = (string) (env('TEST_CURSEFORGE_FILE_ID') ?? '');

        if (! $apiKey || ! $projectId || ! $fileId) {
            $this->markTestSkipped('CurseForge integration test skipped: missing API key or env TEST_CURSEFORGE_PROJECT_ID/TEST_CURSEFORGE_FILE_ID');
        }

        $server = Server::query()->create([
            'name' => 'CF Int',
            'host' => 'localhost',
            'port' => 22,
            'username' => 'user',
            'auth_type' => 'password',
            'password' => 'pass',
            'remote_root_path' => '/',
            'provider' => 'curseforge',
            'provider_pack_id' => $projectId,
        ]);

        $provider = new CurseForgeProvider;

        $versions = $provider->listVersions($server->provider_pack_id);
        $this->assertIsArray($versions);
        $this->assertNotEmpty($versions);

        $src = $provider->fetchSource($server->provider_pack_id, $fileId);
        $this->assertSame('zip', $src['type']);
        $this->assertFileExists($src['path']);
    }
}
