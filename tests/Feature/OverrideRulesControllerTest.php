<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OverrideRuleType;
use App\Models\OverrideRule;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

        Livewire::test(\App\Filament\Resources\OverrideRules\Pages\CreateOverrideRule::class)
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

    public function test_file_add_rule_forces_wildcard_path_patterns(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);

        $upload = UploadedFile::fake()->create('extra.jar', 20);

        Repeater::fake();

        Livewire::test(\App\Filament\Resources\OverrideRules\Pages\CreateOverrideRule::class)
            ->fillForm([
                'name' => 'AddExtraFile',
                'description' => 'Add an extra mod file',
                'scope' => 'global',
                'servers' => [],
                'path_patterns' => ['config/custom.json'],
                'type' => OverrideRuleType::FileAdd->value,
                'add_files' => [
                    [
                        'to' => 'mods/extra.jar',
                        'from_upload' => $upload,
                    ],
                ],
                'overwrite' => true,
                'enabled' => true,
                'priority' => 20,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $rule = OverrideRule::query()->where('name', 'AddExtraFile')->firstOrFail();

        $this->assertSame(['*'], $rule->path_patterns);
        $this->assertSame('file_add', $rule->type->value);
        $this->assertTrue($rule->payload['overwrite']);
        $this->assertSame('mods/extra.jar', $rule->payload['files'][0]['to']);
        $this->assertTrue(Storage::disk('local')->exists($rule->payload['files'][0]['from_upload'][0]));
    }
}
