<?php

declare(strict_types=1);

namespace Tests\Feature;

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

    public function test_it_applies_legacy_file_add_rule(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Setup source files
        $disk->put('source/config.json', '{}');
        $disk->put('uploads/test.jar', 'binary content');

        $server = Server::factory()->create();
        $release = Release::factory()->create(['server_id' => $server->id]);

        $rule = OverrideRule::create([
            'name' => 'Add Mod',
            'type' => 'file_add',
            'scope' => 'global',
            'enabled' => true,
            'path_patterns' => ['*'],
            'payload' => [
                'from_upload' => 'uploads/test.jar',
                'to' => 'mods/test.jar',
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
                    ['from_upload' => 'uploads/mod.jar', 'to' => 'mods/mod.jar'],
                    ['from_upload' => 'uploads/config.cfg', 'to' => 'config/extra.cfg'],
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
                    ['from_upload' => 'uploads/new_config.json', 'to' => 'config.json'],
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
                    ['from_upload' => 'uploads/new_config.json', 'to' => 'config.json'],
                ],
                'overwrite' => true,
            ],
        ]);

        $applier->apply($release, 'source', 'prepared_overwrite');
        $this->assertEquals('new', $disk->get('prepared_overwrite/config.json'));
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
}
