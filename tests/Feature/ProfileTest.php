<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'currentPassword' => 'password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'password' => 'new-password',
                'passwordConfirmation' => 'new-password',
                'currentPassword' => 'password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password', $user->refresh()->password));
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($user);

        // Filament's default profile page does not support account deletion.
        $this->markTestSkipped('Filament default profile does not support account deletion.');
    }
}
