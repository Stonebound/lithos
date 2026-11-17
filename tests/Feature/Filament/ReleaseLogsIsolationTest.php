<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\Release;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReleaseLogsIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function edit_page_only_shows_logs_of_the_viewed_release(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'maintainer']));
        $server = Server::factory()->create();
        $first = Release::factory()->create(['server_id' => $server->id]);
        $second = Release::factory()->create(['server_id' => $server->id]);

        foreach ([$first, $second] as $release) {
            $release->logs()->create(['message' => "log-of-release-{$release->id}", 'level' => 'info']);
        }

        $this->get("/releases/{$second->id}/edit")
            ->assertOk()
            ->assertSee("log-of-release-{$second->id}")
            ->assertDontSee("log-of-release-{$first->id}");
    }
}
