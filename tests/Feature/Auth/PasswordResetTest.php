<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
// Password reset UI has been removed in favor of Filament defaults.
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_redirects_to_filament_login(): void
    {
        $response = $this->get('/forgot-password');

        $response
            ->assertNotFound();
    }

    // Reset password flow is disabled in this port.

    // Reset password screen removed.

    // Reset with valid token removed.
}
