<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Server>
 */
class ServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'host' => $this->faker->ipv4(),
            'port' => 22,
            'username' => 'root',
            'auth_type' => 'password',
            'remote_root_path' => '/home/minecraft',
        ];
    }
}
