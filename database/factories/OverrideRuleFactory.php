<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OverrideRuleType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OverrideRuleFactory>
 */
class OverrideRuleFactory extends Factory
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
            'scope' => 'global',
            'path_patterns' => ['*'],
            'type' => OverrideRuleType::TextReplace,
            'enabled' => $this->faker->boolean(80),
            'payload' => ['search' => 'foo', 'replace' => 'bar'],
        ];
    }

    /**
     * Indicate that the override rule is of type TextReplace.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OverrideRule>
     */
    public function textReplace(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => OverrideRuleType::TextReplace,
                'payload' => ['search' => 'foo', 'replace' => 'bar'],
            ];
        });
    }

    /**
     * Indicate that the override rule is of type FileSkip.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OverrideRule>
     */
    public function fileSkip(array $patterns): Factory
    {
        return $this->state(function (array $attributes) use ($patterns) {
            return [
                'pattern' => $patterns,
                'type' => OverrideRuleType::FileSkip,
                'payload' => null,
            ];
        });
    }
}
