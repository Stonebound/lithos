<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_audit_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        AuditLog::create([
            'model_type' => Server::class,
            'model_id' => 1,
            'action' => 'create',
        ]);

        $this->get('/audit-logs')
            ->assertOk();
    }

    public function test_non_admin_cannot_access_audit_logs(): void
    {
        $maintainer = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($maintainer);

        $this->get('/audit-logs')
            ->assertForbidden();
    }

    public function test_viewer_cannot_access_audit_logs(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer);

        $this->get('/audit-logs')
            ->assertForbidden();
    }
}
