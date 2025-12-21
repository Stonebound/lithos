<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\OverrideRules\Pages\EditOverrideRule;
use App\Models\OverrideRule;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OverrideRulesActionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function toggle_enabled_and_change_priority(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Srv',
            'host' => 'localhost',
            'port' => 22,
            'username' => 'user',
            'auth_type' => 'password',
            'password' => 'pass',
            'remote_root_path' => storage_path('app/test-server-remote'),
            'include_paths' => [],
        ]);

        /** @var OverrideRule $rule */
        $rule = OverrideRule::query()->create([
            'name' => 'Test',
            'scope' => 'server',
            'server_id' => $server->id,
            'path_pattern' => '*.txt',
            'type' => 'text_replace',
            'payload' => ['search' => 'a', 'replace' => 'b', 'regex' => false],
            'enabled' => true,
            'priority' => 5,
        ]);

        Livewire::test(EditOverrideRule::class, ['record' => $rule->getKey()])
            ->callAction('toggleEnabled')
            ->assertHasNoActionErrors()
            ->callAction('raisePriority')
            ->assertHasNoActionErrors()
            ->callAction('lowerPriority')
            ->assertHasNoActionErrors();

        $rule = $rule->refresh();
        $this->assertFalse((bool) $rule->enabled);
        $this->assertSame(5, (int) $rule->priority); // +1 then -1 nets same
    }
}
