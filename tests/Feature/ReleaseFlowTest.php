<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use App\Services\SftpService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use phpseclib3\Net\SFTP;
use Tests\TestCase;

class ReleaseFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function makeZipWithFiles(array $files): string
    {
        $path = Storage::disk('local')->path('test-'.uniqid().'.zip');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }

    public function test_prepare_release_and_deploy_with_mock_sftp(): void
    {
        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);

        $server = Server::query()->create([
            'name' => 'Test',
            'host' => 'example.org',
            'port' => 22,
            'username' => 'user',
            'auth_type' => 'password',
            'password' => 'pw',
            'remote_root_path' => '/srv/mc',
            'include_paths' => [],
        ]);

        // Fake SFTP service
        $fake = new class extends SftpService
        {
            public function connect(Server $server): SFTP
            {
                return new SFTP('localhost');
            }

            public function downloadDirectory(SFTP $sftp, string $remotePath, string $localPath, array $includeTopDirs = [], int $depth = 0, array $skipPatterns = [], string $accumulatedPath = ''): void
            {
                // Create a remote snapshot with one file using Storage
                $root = Storage::disk('local')->path('');
                $localRel = ltrim(str_replace($root, '', $localPath), '/');
                Storage::disk('local')->makeDirectory($localRel.'/config');
                Storage::disk('local')->put($localRel.'/config/game.json', json_encode(['feature' => ['enabled' => false]]));
            }

            public function syncDirectory(SFTP $sftp, string $localPath, string $remotePath, array $skipPatterns = [], ?callable $onProgress = null): void
            {
                // Assert prepared files exist
                if (! Storage::disk('local')->exists($localPath.'/config/game.json')) {
                    throw new \RuntimeException('Prepared path missing');
                }
            }
        };
        $this->app->instance(SftpService::class, $fake);

        // Create zip with updated config and a binary jar
        $zipPath = $this->makeZipWithFiles([
            'config/game.json' => json_encode(['feature' => ['enabled' => true]]),
            'mods/example.jar' => 'BINARYJAR',
        ]);

        // Create release via Filament and upload zip
        Storage::fake('local');
        $zipBytes = file_get_contents($zipPath);
        if ($zipBytes === false) {
            $this->fail('Failed to read generated zip for upload');
        }
        Storage::disk('local')->delete($zipPath);
        $uploaded = UploadedFile::fake()->createWithContent('modpack.zip', $zipBytes);

        Filament::setCurrentPanel('admin');
        Livewire::test(\App\Filament\Resources\Releases\Pages\CreateRelease::class)
            ->fillForm([
                'server_id' => $server->id,
                'version_label' => '1.0.0',
                'source_zip' => $uploaded,
            ])
            ->call('create')
            ->assertHasNoActionErrors();

        // Prepare and deploy via Filament actions on the edit page
        $release = \App\Models\Release::query()->latest()->first();
        Livewire::test(\App\Filament\Resources\Releases\Pages\EditRelease::class, ['record' => $release->getKey()])
            ->callAction('prepare')
            ->assertHasNoActionErrors();

        Livewire::test(\App\Filament\Resources\Releases\Pages\EditRelease::class, ['record' => $release->getKey()])
            ->callAction('deploy', ['confirmation' => 'DEPLOY NOW'])
            ->assertHasNoActionErrors();
    }
}
