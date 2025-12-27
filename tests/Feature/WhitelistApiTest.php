<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\WhitelistUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WhitelistApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_whitelist_json_and_txt_endpoints(): void
    {
        // create some whitelist entries
        WhitelistUser::create(['uuid' => '11111111-1111-1111-1111-111111111111', 'username' => 'alice', 'source' => 'test']);
        WhitelistUser::create(['uuid' => '22222222-2222-2222-2222-222222222222', 'username' => 'bob', 'source' => 'test']);

        $this->get('/whitelist.json')
            ->assertOk()
            ->assertJsonStructure([['uuid', 'name']])
            ->assertJsonFragment(['uuid' => '11111111-1111-1111-1111-111111111111', 'name' => 'alice'])
            ->assertJsonFragment(['uuid' => '22222222-2222-2222-2222-222222222222', 'name' => 'bob']);

        $this->get('/whitelist.txt')->assertOk()->assertSee('11111111-1111-1111-1111-111111111111')->assertSee('22222222-2222-2222-2222-222222222222');
    }

    public function test_api_whitelist_requires_api_key_and_adds_user(): void
    {
        // set known API key
        Config::set('app.api_auth_key', 'test-key');

        // missing key should be unauthorized
        $this->postJson('/api/whitelist', ['username' => 'charlie'])->assertStatus(401);

        // wrong key
        $this->withHeaders(['api-key' => 'wrong'])->postJson('/api/whitelist', ['username' => 'charlie'])->assertStatus(401);

        // correct key should create an audit log and whitelist user (service resolves uuid using MinecraftApi; we'll stub it)
        $this->mock(\App\Services\MinecraftApi::class, function ($mock) {
            $mock->shouldReceive('uuidForName')->with('charlie')->andReturn('33333333-3333-3333-3333-333333333333');
        });

        $this->withHeaders(['api-key' => 'test-key', 'api-user' => 'DiscordBot'])->postJson('/api/whitelist', ['name' => 'charlie'])
            ->assertStatus(201)
            ->assertJsonFragment([
                'status' => 'success',
                'message' => 'charlie has been whitelisted!',
                'uuid' => '33333333-3333-3333-3333-333333333333',
            ]);

        $this->assertDatabaseHas('whitelist_users', ['username' => 'charlie', 'uuid' => '33333333-3333-3333-3333-333333333333']);
    }
}
