<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReleaseStatus;
use App\Models\AuditLog;
use App\Models\OverrideRule;
use App\Models\Release;
use App\Models\Server;
use App\Models\User;
use App\Models\WhitelistUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    private function requireAuditLog(string $modelType, string $action, ?int $modelId = null): AuditLog
    {
        $query = AuditLog::query()
            ->where('model_type', $modelType)
            ->where('action', $action);

        if ($modelId !== null) {
            $query->where('model_id', $modelId);
        }

        $log = $query->first();

        $this->assertInstanceOf(AuditLog::class, $log);

        return $log;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireAuditValues(?array $values): array
    {
        $this->assertIsArray($values);

        /** @var array<string, mixed> $values */
        return $values;
    }

    public function test_server_create_logs_audit(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user);

        Server::create([
            'name' => 'Test Server',
            'host' => 'example.com',
            'port' => 22,
            'username' => 'user',
            'auth_type' => 'password',
            'password' => 'pass',
            'remote_root_path' => '/path',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'model_type' => Server::class,
            'action' => 'create',
        ]);
    }

    public function test_release_create_logs_audit(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $server = Server::factory()->create();

        $this->actingAs($user);

        Release::create([
            'server_id' => $server->id,
            'name' => 'Test Release',
            'status' => ReleaseStatus::Draft,
            'source_type' => 'dir',
            'source_path' => '/tmp/test',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'model_type' => Release::class,
            'action' => 'create',
        ]);
    }

    public function test_server_update_logs_audit(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $server = Server::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user);

        $server->update(['name' => 'New Name']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'model_type' => Server::class,
            'action' => 'update',
        ]);

        $log = $this->requireAuditLog(Server::class, 'update');
        $oldValues = $this->requireAuditValues($log->old_values);
        $newValues = $this->requireAuditValues($log->new_values);

        $this->assertArrayHasKey('name', $oldValues);
        $this->assertArrayHasKey('name', $newValues);
        $this->assertEquals('Old Name', $oldValues['name']);
        $this->assertEquals('New Name', $newValues['name']);
    }

    public function test_user_create_logs_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'role' => 'viewer',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'model_type' => User::class,
            'action' => 'create',
        ]);
    }

    public function test_override_rule_create_logs_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        OverrideRule::create([
            'name' => 'Test Rule',
            'path_patterns' => ['*.json'],
            'type' => 'text_replace',
            'payload' => ['search' => 'old', 'replace' => 'new'],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'model_type' => OverrideRule::class,
            'action' => 'create',
        ]);
    }

    public function test_hidden_attributes_are_logged_but_censored(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        $email = 'test'.time().'@example.com';

        User::create([
            'name' => 'Test User',
            'email' => $email,
            'password' => 'secretpassword',
            'role' => 'viewer',
        ]);

        $log = AuditLog::where('model_type', User::class)->where('action', 'create')->whereJsonContains('new_values->email', $email)->first();
        $this->assertInstanceOf(AuditLog::class, $log);
        $newValues = $this->requireAuditValues($log->new_values);

        // Ensure password is logged but censored
        $this->assertArrayHasKey('password', $newValues);
        $this->assertEquals('***CHANGED***', $newValues['password']);

        // Ensure other attributes are still logged normally
        $this->assertArrayHasKey('name', $newValues);
        $this->assertArrayHasKey('email', $newValues);
        $this->assertArrayHasKey('role', $newValues);
        $this->assertEquals('Test User', $newValues['name']);
        $this->assertEquals($email, $newValues['email']);
        $this->assertEquals('viewer', $newValues['role']);
    }

    public function test_whitelist_user_create_update_delete_logs_audit(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);

        $this->actingAs($user);

        // Create
        $wu = WhitelistUser::create([
            'uuid' => '00000000-0000-0000-0000-000000000000',
            'username' => 'notch',
            'source' => 'test',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'model_type' => WhitelistUser::class,
            'action' => 'create',
        ]);

        // Update
        $wu->update(['username' => 'notch_new']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'model_type' => WhitelistUser::class,
            'action' => 'update',
        ]);

        // Delete
        $wu->delete();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'model_type' => WhitelistUser::class,
            'action' => 'delete',
        ]);
    }

    public function test_update_logs_only_changed_attributes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'role' => 'viewer',
        ]);

        // Update only the name
        $user->update(['name' => 'Updated Name']);

        $log = $this->requireAuditLog(User::class, 'update', $user->id);
        $oldValues = $this->requireAuditValues($log->old_values);
        $newValues = $this->requireAuditValues($log->new_values);

        // Should only log the changed attribute
        $this->assertArrayHasKey('name', $oldValues);
        $this->assertArrayHasKey('name', $newValues);
        $this->assertEquals('Original Name', $oldValues['name']);
        $this->assertEquals('Updated Name', $newValues['name']);

        // Should not log unchanged attributes
        $this->assertArrayNotHasKey('email', $oldValues);
        $this->assertArrayNotHasKey('email', $newValues);
        $this->assertArrayNotHasKey('role', $oldValues);
        $this->assertArrayNotHasKey('role', $newValues);
    }
}
