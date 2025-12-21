<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Release>
 */
class ReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => \App\Models\Server::factory(),
            'version_label' => '1.0.0',
            'source_type' => 'dir',
            'source_path' => 'source',
            'status' => \App\Enums\ReleaseStatus::Draft,
        ];
    }
}
