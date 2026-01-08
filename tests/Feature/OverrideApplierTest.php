<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MinecraftVersion;
use App\Models\OverrideRule;
use App\Models\Release;
use App\Models\Server;
use App\Services\OverrideApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OverrideApplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_minecraft_version_specific_rules_only_when_matching(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        $disk->put('source/config.txt', 'Hello Old');

        $matchingVersion = MinecraftVersion::create([
            'id' => '1.20.1',
            'release_time' => now(),
        ]);

        $server = Server::factory()->create([
            'minecraft_version' => $matchingVersion->id,
        ]);

        $release = Release::factory()->create(['server_id' => $server->id]);

        OverrideRule::create([
            'name' => 'Non-matching rule should not apply',
            'type' => 'text_replace',
            'scope' => 'global',
            'enabled' => true,
            'priority' => 100,
            'minecraft_version' => '1\.21\.0',
            'path_patterns' => ['config.txt'],
            'payload' => [
                'search' => 'Hello',
                'replace' => 'WRONG',
            ],
        ]);

        OverrideRule::create([
            'name' => 'Matching rule applies',
            'type' => 'text_replace',
            'scope' => 'global',
            'enabled' => true,
            'priority' => 0,
            'minecraft_version' => '1\.20\..',
            'path_patterns' => ['config.txt'],
            'payload' => [
                'search' => 'Old',
                'replace' => 'New',
            ],
        ]);

        $applier = new OverrideApplier;
        $applier->apply($release, 'source', 'prepared');

        $this->assertTrue($disk->exists('prepared/config.txt'));
        $this->assertEquals('Hello New', $disk->get('prepared/config.txt'));
        $this->assertStringNotContainsString('WRONG', $disk->get('prepared/config.txt'));
    }

    public function test_it_only_applies_unscoped_rules_when_server_has_no_minecraft_version(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        $disk->put('source/config.txt', 'Hello Old');

        $version = MinecraftVersion::create([
            'id' => '1.20.1',
            'release_time' => now(),
        ]);

        $server = Server::factory()->create([
            'minecraft_version' => null,
        ]);

        $release = Release::factory()->create(['server_id' => $server->id]);

        OverrideRule::create([
            'name' => 'Version-scoped rule should not apply',
            'type' => 'text_replace',
            'scope' => 'global',
            'enabled' => true,
            'priority' => 100,
            'minecraft_version' => $version->id,
            'path_patterns' => ['config.txt'],
            'payload' => [
                'search' => 'Old',
                'replace' => 'WRONG',
            ],
        ]);

        OverrideRule::create([
            'name' => 'Unscoped rule applies',
            'type' => 'text_replace',
            'scope' => 'global',
            'enabled' => true,
            'priority' => 0,
            'minecraft_version' => null,
            'path_patterns' => ['config.txt'],
            'payload' => [
                'search' => 'Old',
                'replace' => 'New',
            ],
        ]);

        $applier = new OverrideApplier;
        $applier->apply($release, 'source', 'prepared');

        $this->assertTrue($disk->exists('prepared/config.txt'));
        $this->assertEquals('Hello New', $disk->get('prepared/config.txt'));
        $this->assertStringNotContainsString('WRONG', $disk->get('prepared/config.txt'));
    }

    public function test_it_applies_file_add_rule(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Setup source files
        $disk->put('source/config.json', '{}');
        $disk->put('uploads/test.jar', 'binary content');

        $server = Server::factory()->create();
        $release = Release::factory()->create(['server_id' => $server->id]);

        OverrideRule::create([
            'name' => 'Add Mod',
            'type' => 'file_add',
            'scope' => 'global',
            'enabled' => true,
            'path_patterns' => ['*'],
            'payload' => [
                'files' => [
                    ['from_upload' => ['uploads/test.jar'], 'to' => 'mods/test.jar'],
                ],
                'overwrite' => true,
            ],
        ]);

        $applier = new OverrideApplier;
        $applier->apply($release, 'source', 'prepared');

        $this->assertTrue($disk->exists('prepared/config.json'));
        $this->assertTrue($disk->exists('prepared/mods/test.jar'));
        $this->assertEquals('binary content', $disk->get('prepared/mods/test.jar'));
    }

    public function test_it_applies_multi_file_add_rule(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Setup source files
        $disk->put('source/config.json', '{}');
        $disk->put('uploads/mod.jar', 'mod content');
        $disk->put('uploads/config.cfg', 'config content');

        $server = Server::factory()->create();
        $release = Release::factory()->create(['server_id' => $server->id]);

        $rule = OverrideRule::create([
            'name' => 'Add Mod and Config',
            'type' => 'file_add',
            'scope' => 'global',
            'enabled' => true,
            'path_patterns' => ['*'],
            'payload' => [
                'files' => [
                    ['from_upload' => ['uploads/mod.jar'], 'to' => 'mods/mod.jar'],
                    ['from_upload' => ['uploads/config.cfg'], 'to' => 'config/extra.cfg'],
                ],
                'overwrite' => true,
            ],
        ]);

        $applier = new OverrideApplier;
        $applier->apply($release, 'source', 'prepared');

        $this->assertTrue($disk->exists('prepared/mods/mod.jar'));
        $this->assertTrue($disk->exists('prepared/config/extra.cfg'));
        $this->assertEquals('mod content', $disk->get('prepared/mods/mod.jar'));
        $this->assertEquals('config content', $disk->get('prepared/config/extra.cfg'));
    }

    public function test_it_respects_overwrite_flag(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Setup source files
        $disk->put('source/config.json', 'original');
        $disk->put('uploads/new_config.json', 'new');

        $server = Server::factory()->create();
        $release = Release::factory()->create(['server_id' => $server->id]);

        // Rule with overwrite = false
        $rule = OverrideRule::create([
            'name' => 'Add Config No Overwrite',
            'type' => 'file_add',
            'scope' => 'global',
            'enabled' => true,
            'path_patterns' => ['*'],
            'payload' => [
                'files' => [
                    ['from_upload' => ['uploads/new_config.json'], 'to' => 'config.json'],
                ],
                'overwrite' => false,
            ],
        ]);

        $applier = new OverrideApplier;
        $applier->apply($release, 'source', 'prepared');

        // Should still be original because it was copied from source first
        $this->assertEquals('original', $disk->get('prepared/config.json'));

        // Now with overwrite = true
        $rule->update([
            'payload' => [
                'files' => [
                    ['from_upload' => ['uploads/new_config.json'], 'to' => 'config.json'],
                ],
                'overwrite' => true,
            ],
        ]);

        $applier->apply($release, 'source', 'prepared_overwrite');
        $this->assertEquals('new', $disk->get('prepared_overwrite/config.json'));
    }

    public function test_it_keeps_and_modifies_remote_files_if_rule_matches(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Setup source files (modpack doesn't have config/motd.toml)
        $disk->put('source/mods/mod1.jar', 'content');

        // Setup remote files (server has config/motd.toml)
        $disk->put('remote/config/motd.toml', 'motd = Old MOTD');
        $disk->put('remote/other.txt', 'keep me');

        $server = Server::factory()->create();
        $release = Release::factory()->create(['server_id' => $server->id]);

        // Rule to modify config/motd.toml
        OverrideRule::create([
            'name' => 'Update MOTD',
            'type' => 'text_replace',
            'scope' => 'global',
            'enabled' => true,
            'path_patterns' => ['config/motd.toml'],
            'payload' => [
                'search' => 'Old MOTD',
                'replace' => 'New MOTD',
            ],
        ]);

        $applier = new OverrideApplier;
        $applier->apply($release, 'source', 'prepared', 'remote');

        // config/motd.toml should be in prepared dir and modified
        $this->assertTrue($disk->exists('prepared/config/motd.toml'));
        $this->assertEquals('motd = New MOTD', $disk->get('prepared/config/motd.toml'));

        // other.txt should NOT be in prepared dir (it will be marked as removed by DiffService)
        $this->assertFalse($disk->exists('prepared/other.txt'));

        // mod1.jar should still be there
        $this->assertTrue($disk->exists('prepared/mods/mod1.jar'));
    }

    public function test_it_applies_multiple_path_patterns(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Setup source files
        $disk->put('source/config/app.json', '{"key": "old"}');
        $disk->put('source/settings/web.json', '{"key": "old"}');
        $disk->put('source/other.txt', 'no change');

        $server = Server::factory()->create();
        $release = Release::factory()->create(['server_id' => $server->id]);

        $rule = OverrideRule::create([
            'name' => 'Patch Multiple JSONs',
            'type' => 'json_patch',
            'scope' => 'global',
            'enabled' => true,
            'path_patterns' => ['config/*.json', 'settings/*.json'],
            'payload' => [
                'merge' => ['key' => 'new'],
            ],
        ]);

        $applier = new OverrideApplier;
        $applier->apply($release, 'source', 'prepared');

        $this->assertEquals(['key' => 'new'], json_decode($disk->get('prepared/config/app.json'), true));
        $this->assertEquals(['key' => 'new'], json_decode($disk->get('prepared/settings/web.json'), true));
        $this->assertEquals('no change', $disk->get('prepared/other.txt'));
    }

    public function test_it_handles_multiple_skip_patterns(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Setup source files
        $disk->put('source/logs/latest.log', 'log');
        $disk->put('source/temp/cache.tmp', 'cache');
        $disk->put('source/important.txt', 'keep');

        $server = Server::factory()->create();
        $release = Release::factory()->create(['server_id' => $server->id]);

        $rule = OverrideRule::create([
            'name' => 'Skip Logs and Temp',
            'type' => 'file_skip',
            'scope' => 'global',
            'enabled' => true,
            'path_patterns' => ['logs/*.log', 'temp/*'],
            'payload' => [],
        ]);

        $applier = new OverrideApplier;
        $applier->apply($release, 'source', 'prepared');

        $this->assertFalse($disk->exists('prepared/logs/latest.log'));
        $this->assertFalse($disk->exists('prepared/temp/cache.tmp'));
        $this->assertTrue($disk->exists('prepared/important.txt'));
    }

    public function test_it_skips_binary_files_in_remote_copy(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Setup source files
        $disk->put('source/mods/mod1.jar', 'content');

        // Setup remote files: one text, one binary
        $disk->put('remote/config/test.txt', 'some text');
        $disk->put('remote/config/binary.dat', "binary\0content");

        $server = Server::factory()->create();
        $release = Release::factory()->create(['server_id' => $server->id]);

        // Rule that matches both
        OverrideRule::create([
            'name' => 'Match All Config',
            'type' => 'text_replace',
            'scope' => 'global',
            'enabled' => true,
            'path_patterns' => ['config/*'],
            'payload' => [
                'search' => 'text',
                'replace' => 'modified',
            ],
        ]);

        $applier = new OverrideApplier;
        $applier->apply($release, 'source', 'prepared', 'remote');

        // test.txt should be copied and modified
        $this->assertTrue($disk->exists('prepared/config/test.txt'));
        $this->assertEquals('some modified', $disk->get('prepared/config/test.txt'));

        // binary.dat should NOT be copied because it's binary
        $this->assertFalse($disk->exists('prepared/config/binary.dat'));
    }

    public function test_it_skips_files_that_would_not_be_modified(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Setup source files
        $disk->put('source/mods/mod1.jar', 'content');

        // Setup remote files: one that matches and changes, one that matches but doesn't change
        $disk->put('remote/config/change.json', json_encode(['key' => 'old']));
        $disk->put('remote/config/nochange.json', json_encode(['key' => 'new']));

        $server = Server::factory()->create();
        $release = Release::factory()->create(['server_id' => $server->id]);

        // Rule to set key to new
        OverrideRule::create([
            'name' => 'Set Key to New',
            'type' => 'json_patch',
            'scope' => 'global',
            'enabled' => true,
            'path_patterns' => ['config/*.json'],
            'payload' => [
                'merge' => ['key' => 'new'],
            ],
        ]);

        $applier = new OverrideApplier;
        $applier->apply($release, 'source', 'prepared', 'remote');

        // change.json should be copied and modified
        $this->assertTrue($disk->exists('prepared/config/change.json'));
        $data = json_decode($disk->get('prepared/config/change.json'), true);
        $this->assertEquals('new', $data['key']);

        // nochange.json should NOT be copied because it already has the value
        $this->assertFalse($disk->exists('prepared/config/nochange.json'));
    }
}
