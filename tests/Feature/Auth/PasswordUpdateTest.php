<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
// The profile password update UI has been removed.
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated_programmatically(): void
    {
        $user = User::factory()->create();

        $user->forceFill(['password' => bcrypt('new-password')])->save();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    // Correct password validation is part of removed UI; not applicable.
}
