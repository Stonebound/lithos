<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Releases\Pages\CreateRelease;
use App\Models\Server;
use App\Models\User;
use App\Services\Providers\ProviderInterface;
use App\Services\Providers\ProviderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReleaseFormDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function defaults_to_provider_if_available_and_sets_version_label(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);

        /** @var Server $serverWithProvider */
        $serverWithProvider = Server::query()->create([
            'name' => 'Has Provider',
            'host' => 'localhost',
            'port' => 22,
            'username' => 'user',
            'auth_type' => 'password',
            'password' => 'pass',
            'remote_root_path' => '/',
            'include_paths' => [],
            'provider' => 'ftb',
            'provider_pack_id' => 'pkg-1',
        ]);

        /** @var Server $serverNoProvider */
        $serverNoProvider = Server::query()->create([
            'name' => 'No Provider',
            'host' => 'localhost',
            'port' => 22,
            'username' => 'user',
            'auth_type' => 'password',
            'password' => 'pass',
            'remote_root_path' => '/',
            'include_paths' => [],
            'provider' => null,
            'provider_pack_id' => null,
        ]);

        $fakeProvider = new class implements ProviderInterface
        {
            public function listVersions(string|int $providerPackId): array
            {
                return [
                    ['id' => 'v1', 'name' => 'Version One'],
                    ['id' => 'v2', 'name' => 'Version Two'],
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

        // Mount create page and set server with provider
        Livewire::test(CreateRelease::class)
            ->set('data.server_id', $serverWithProvider->id)
            ->assertSet('data.source_mode', 'provider')
            ->set('data.provider_version_id', 'v2')
            ->assertSet('data.version_label', 'Version Two')
            // Switch to server without provider, ensure source_mode flips
            ->set('data.server_id', $serverNoProvider->id)
            ->assertSet('data.source_mode', 'upload');
    }
}
