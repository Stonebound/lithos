<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\OverrideRule;
use App\Models\Release;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OverrideRulesFileOpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_file_add_and_remove_rules_apply(): void
    {
        // Server and source
        $sourceRel = 'test-source-files';
        $sourceDir = Storage::disk('local')->path($sourceRel);
        Storage::disk('local')->makeDirectory($sourceRel.'/mods');
        Storage::disk('local')->put($sourceRel.'/mods/keep-me.jar', 'existing');
        Storage::disk('local')->put($sourceRel.'/mods/remove-me.jar', 'bye');

        $server = Server::query()->create([
            'name' => 'Srv', 'host' => 'localhost', 'port' => 22, 'username' => 'user', 'auth_type' => 'password', 'password' => 'pass',
            'remote_root_path' => $sourceDir, 'include_paths' => [], 'provider' => null, 'provider_pack_id' => null,
        ]);

        /** @var Release $release */
        $release = Release::query()->create([
            'server_id' => $server->id,
            'status' => 'draft',
            'source_type' => 'dir',
            'source_path' => $sourceDir,
        ]);

        // Fake SFTP service to avoid real connections and create snapshot by copying local source
        $fakeSftp = new class extends \App\Services\SftpService
        {
            public function connect(Server $server): \phpseclib3\Net\SFTP
            {
                return new \phpseclib3\Net\SFTP('localhost');
            }

            public function downloadDirectory(\phpseclib3\Net\SFTP $sftp, string $remotePath, string $localPath, array $includeTopDirs = [], int $depth = 0, array $skipPatterns = [], string $accumulatedPath = ''): void
            {
                $root = Storage::disk('local')->path('');
                $remoteRel = ltrim(str_replace($root, '', $remotePath), '/');
                $localRel = ltrim(str_replace($root, '', $localPath), '/');
                Storage::disk('local')->makeDirectory($localRel);
                foreach (Storage::disk('local')->allFiles($remoteRel) as $file) {
                    $relative = ltrim(str_replace($remoteRel.'/', '', $file), '/');
                    $targetRel = $localRel.'/'.($relative ?: basename($file));
                    $dir = dirname($targetRel);
                    if ($dir !== '.' && ! Storage::disk('local')->exists($dir)) {
                        Storage::disk('local')->makeDirectory($dir);
                    }
                    Storage::disk('local')->put($targetRel, Storage::disk('local')->get($file));
                }
            }
        };
        $this->app->instance(\App\Services\SftpService::class, $fakeSftp);

        // Upload a file to add via rule
        Storage::disk('local')->put('uploads/override-files/extra.jar', 'extra');

        // Add file_add rule (global)
        OverrideRule::query()->create([
            'name' => 'AddExtra', 'scope' => 'global',
            'path_patterns' => ['*'], 'type' => 'file_add', 'payload' => [
                'from_upload' => 'uploads/override-files/extra.jar',
                'to' => 'mods/extra.jar', 'overwrite' => true,
            ], 'enabled' => true, 'priority' => 10,
        ]);

        // Add file_remove rule (global) targeting remove-me.jar
        OverrideRule::query()->create([
            'name' => 'RemoveJar', 'scope' => 'global',
            'path_patterns' => ['mods/remove-me.jar'], 'type' => 'file_remove', 'payload' => [], 'enabled' => true, 'priority' => 5,
        ]);

        // Prepare
        ReleaseResource::prepareRelease($release);
        $release = $release->refresh();

        // Assert prepared contains extra.jar and keep-me.jar, but not remove-me.jar
        $this->assertTrue(Storage::disk('local')->exists($release->prepared_path.'/mods/extra.jar'));
        $this->assertTrue(Storage::disk('local')->exists($release->prepared_path.'/mods/keep-me.jar'));
        $this->assertFalse(Storage::disk('local')->exists($release->prepared_path.'/mods/remove-me.jar'));
    }
}
