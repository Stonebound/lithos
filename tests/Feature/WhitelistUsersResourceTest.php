<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\WhitelistUsers\Pages\CreateWhitelistUser;
use App\Models\User;
use App\Models\WhitelistUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WhitelistUsersResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->get('/whitelist-users')->assertRedirect('/login');
    }

    public function test_list_whitelist_users(): void
    {
        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);

        WhitelistUser::query()->create([
            'username' => 'Steve',
            'uuid' => '8667ba71-b85a-4004-af54-457a9734eed7',
            'source' => 'manual',
        ]);

        $this->get('/whitelist-users')
            ->assertOk();
    }

    public function test_create_whitelist_user(): void
    {
        $admin = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($admin);

        Livewire::test(CreateWhitelistUser::class)
            ->set('data.username', 'notch')
            ->call('create')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('whitelist_users', [
            'username' => 'notch',
            'uuid' => '069a79f4-44e9-4726-a5be-fca90e38aaf5',
        ]);
    }
}
