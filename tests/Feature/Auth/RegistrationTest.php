<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
// Registration via Breeze UI has been removed in favor of Filament.
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_registration_is_not_available(): void
    {
        $response = $this->get('/register');

        $response
            ->assertNotFound();
    }

    // Registration flow is disabled; users are managed by administrators.
}
