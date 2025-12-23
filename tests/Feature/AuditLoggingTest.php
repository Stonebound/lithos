<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\OverrideRule;
use App\Models\Release;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

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
            'status' => \App\Enums\ReleaseStatus::Draft,
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

        $log = AuditLog::where('model_type', Server::class)->where('action', 'update')->first();
        $this->assertEquals('Old Name', $log->old_values['name']);
        $this->assertEquals('New Name', $log->new_values['name']);
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

        // Ensure password is logged but censored
        $this->assertEquals('***CHANGED***', $log->new_values['password']);

        // Ensure other attributes are still logged normally
        $this->assertEquals('Test User', $log->new_values['name']);
        $this->assertEquals($email, $log->new_values['email']);
        $this->assertEquals('viewer', $log->new_values['role']);
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

        $log = AuditLog::where('model_type', User::class)
            ->where('action', 'update')
            ->where('model_id', $user->id)
            ->first();

        // Should only log the changed attribute
        $this->assertArrayHasKey('name', $log->old_values);
        $this->assertArrayHasKey('name', $log->new_values);
        $this->assertEquals('Original Name', $log->old_values['name']);
        $this->assertEquals('Updated Name', $log->new_values['name']);

        // Should not log unchanged attributes
        $this->assertArrayNotHasKey('email', $log->old_values);
        $this->assertArrayNotHasKey('email', $log->new_values);
        $this->assertArrayNotHasKey('role', $log->old_values);
        $this->assertArrayNotHasKey('role', $log->new_values);
    }
}
