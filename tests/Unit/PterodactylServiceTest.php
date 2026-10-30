<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Server;
use App\Services\PterodactylService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PterodactylServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_extracts_server_id_from_username_pattern(): void
    {
        // Mock the config to return configured values
        config(['services.pterodactyl.api_key' => 'test_key', 'services.pterodactyl.base_url' => 'https://test.com']);

        $service = new PterodactylService;

        // Mock server with Pterodactyl username pattern
        $server = new Server(['username' => 'user.d3aac109']);

        $serverId = $service->extractServerId($server);

        $this->assertEquals('d3aac109', $serverId);
    }

    public function test_returns_null_for_non_pterodactyl_username(): void
    {
        // Mock the config to return configured values
        config(['services.pterodactyl.api_key' => 'test_key', 'services.pterodactyl.base_url' => 'https://test.com']);

        $service = new PterodactylService;

        // Mock server with regular username
        $server = new Server(['username' => 'root']);

        $serverId = $service->extractServerId($server);

        $this->assertNull($serverId);
    }

    public function test_identifies_pterodactyl_servers_by_username_pattern(): void
    {
        // Mock the config to return configured values
        config(['services.pterodactyl.api_key' => 'test_key', 'services.pterodactyl.base_url' => 'https://test.com']);

        $service = new PterodactylService;

        // Mock server with Pterodactyl username pattern
        $pterodactylServer = new Server(['username' => 'user.d3aac109']);
        // Mock server with regular username
        $regularServer = new Server(['username' => 'deployer']);

        $this->assertTrue($service->isPterodactylServer($pterodactylServer));
        $this->assertFalse($service->isPterodactylServer($regularServer));
    }

    public function test_handles_partial_uuid_in_username(): void
    {
        // Mock the config to return configured values
        config(['services.pterodactyl.api_key' => 'test_key', 'services.pterodactyl.base_url' => 'https://test.com']);

        $service = new PterodactylService;

        // Mock server with partial UUID (first 8 characters)
        $server = new Server(['username' => 'user.d3aac109']);

        $serverId = $service->extractServerId($server);

        $this->assertEquals('d3aac109', $serverId);
    }

    public function test_extracts_server_id_for_any_panel_username(): void
    {
        config(['services.pterodactyl.api_key' => 'test_key', 'services.pterodactyl.base_url' => 'https://test.com']);

        $service = new PterodactylService;

        $this->assertSame('d3aac109', $service->extractServerId(new Server(['username' => 'phit.d3aac109'])));
        $this->assertSame(
            'd3aac109-1c2b-4f5e-9a8b-0123456789ab',
            $service->extractServerId(new Server(['username' => 'phit.d3aac109-1c2b-4f5e-9a8b-0123456789ab'])),
        );
    }

    public function test_does_not_treat_plain_or_malformed_usernames_as_pterodactyl(): void
    {
        config(['services.pterodactyl.api_key' => 'test_key', 'services.pterodactyl.base_url' => 'https://test.com']);

        $service = new PterodactylService;

        foreach (['root', 'd3aac109', '.d3aac109', 'phit.d3aac', 'phit.notahexid', 'phit.d3aac109.extra', 'first.last.d3aac109'] as $username) {
            $this->assertFalse($service->isPterodactylServer(new Server(['username' => $username])), $username);
        }
    }

    public function test_is_not_a_pterodactyl_server_when_integration_is_unconfigured(): void
    {
        config(['services.pterodactyl.api_key' => null, 'services.pterodactyl.base_url' => null]);

        $this->assertFalse((new PterodactylService)->isPterodactylServer(new Server(['username' => 'phit.d3aac109'])));
    }
}
