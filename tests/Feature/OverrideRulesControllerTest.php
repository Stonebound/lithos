<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverrideRulesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->get('/overrides')->assertNotFound();
        // After login, legacy route redirects to Filament panel at root.
        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);
        $this->get('/overrides')->assertNotFound();
    }

    public function test_create_override_rule(): void
    {
        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);

        \Livewire\Livewire::test(\App\Filament\Resources\OverrideRules\Pages\CreateOverrideRule::class)
            ->set('data.name', 'EnableFeature')
            ->set('data.description', 'Turn on feature')
            ->set('data.scope', 'global')
            ->set('data.servers', [])
            ->set('data.path_patterns', ['config/**/*.json'])
            ->set('data.type', 'json_patch')
            ->set('data.payload', ['merge' => ['feature' => ['enabled' => true]]])
            ->set('data.enabled', true)
            ->set('data.priority', 10)
            ->call('create')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('override_rules', ['name' => 'EnableFeature', 'type' => 'json_patch']);
    }
}
