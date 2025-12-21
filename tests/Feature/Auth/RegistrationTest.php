<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
// Registration via Breeze UI has been removed in favor of Filament.
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available_and_redirects_to_filament_login(): void
    {
        $response = $this->get('/register');

        $response
            ->assertNotFound();
    }

    // Registration flow is disabled; users are managed by administrators.
}
