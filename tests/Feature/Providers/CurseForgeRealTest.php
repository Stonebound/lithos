<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Models\Server;
use App\Services\Providers\CurseForgeProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CurseForgeRealTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_curseforge_pack_fetches_zip(): void
    {
        if (! filter_var(env('RUN_EXTERNAL_TESTS'), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('External tests disabled. Set RUN_EXTERNAL_TESTS=true to run.');
        }

        // Provided by user
        $apiKey = '$2a$10$bL4bIL5pUWqfcO7KQtnMReakwtfHbNKh6v1uTpKlzhwoueEJQnPnm';
        $projectId = 1190911;

        Config::set('services.curseforge.key', $apiKey);

        $server = Server::query()->create([
            'name' => 'CF Real',
            'host' => 'localhost',
            'port' => 22,
            'username' => 'user',
            'auth_type' => 'password',
            'password' => 'pass',
            'remote_root_path' => '/',
            'provider' => 'curseforge',
            'provider_pack_id' => (string) $projectId,
        ]);

        $provider = new CurseForgeProvider;
        $versions = $provider->listVersions($server->provider_pack_id);
        $this->assertIsArray($versions);
        $this->assertNotEmpty($versions, 'No versions returned for CurseForge project');

        $versionId = $versions[0]['id'];
        $src = $provider->fetchSource($server->provider_pack_id, $versionId);
        $this->assertSame('zip', $src['type']);
        $this->assertFileExists($src['path']);
    }
}
