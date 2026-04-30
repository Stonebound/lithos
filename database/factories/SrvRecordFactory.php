<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SrvRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SrvRecord>
 */
class SrvRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subdomain' => $this->faker->word(),
            'port' => $this->faker->numberBetween(1024, 65535),
        ];
    }
}
