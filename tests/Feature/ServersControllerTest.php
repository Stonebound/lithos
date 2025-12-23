<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServersControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->get('/servers')->assertRedirect('/login');
    }

    public function test_list_servers(): void
    {
        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);

        Server::query()->create([
            'name' => 'Prod',
            'host' => 'example.org',
            'port' => 22,
            'username' => 'deployer',
            'auth_type' => 'password',
            'password' => 'secret',
            'remote_root_path' => '/srv/mc',
        ]);

        $this->get('/servers')
            ->assertOk();
    }

    public function test_create_server(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        Livewire::test(\App\Filament\Resources\Servers\Pages\CreateServer::class)
            ->set('data.name', 'TestSrv')
            ->set('data.host', 'host.local')
            ->set('data.port', 22)
            ->set('data.username', 'user')
            ->set('data.auth_type', 'password')
            ->set('data.password', 'pw')
            ->set('data.remote_root_path', '/srv/root')
            ->call('create')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('servers', ['name' => 'TestSrv', 'host' => 'host.local']);
    }

    public function test_maintainer_cant_create_server(): void
    {
        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);

        Livewire::test(\App\Filament\Resources\Servers\Pages\CreateServer::class)
            ->assertForbidden();
    }
}
