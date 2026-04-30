<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WhitelistUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhitelistUser>
 */
class WhitelistUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'username' => fake()->userName(),
            'source' => null,
        ];
    }
}
