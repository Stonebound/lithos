<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $this->assertGuest();

        $userToAuthenticate = User::factory()->create();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $userToAuthenticate->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertRedirect(Filament::getUrl());

        $this->assertAuthenticatedAs($userToAuthenticate);
        $this->assertNotNull($userToAuthenticate->refresh()->last_logged_in_at);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $this->assertGuest();

        $userToAuthenticate = User::factory()->create();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $userToAuthenticate->email,
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
        $this->assertGuest();
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/');

        $response
            ->assertOk();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Auth::logout();

        $this->assertGuest();
    }
}
