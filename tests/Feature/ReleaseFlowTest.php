<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Releases\Pages\CreateRelease;
use App\Filament\Resources\Releases\Pages\EditRelease;
use App\Models\Release;
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

    /**
     * @param  array<string, string>  $files
     */
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

    public function test_uploaded_zip_is_extracted_into_the_prepared_release(): void
    {
        Storage::fake('local');

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
                Storage::disk('local')->put($localRel.'/config/game.json', json_encode(['feature' => ['enabled' => false]], JSON_THROW_ON_ERROR));
            }
        };
        $this->app->instance(SftpService::class, $fake);

        // Create zip with updated config and a binary jar
        $zipPath = $this->makeZipWithFiles([
            'config/game.json' => json_encode(['feature' => ['enabled' => true]], JSON_THROW_ON_ERROR),
            'mods/example.jar' => 'BINARYJAR',
        ]);
        $zipBytes = file_get_contents($zipPath);
        if ($zipBytes === false) {
            $this->fail('Failed to read generated zip for upload');
        }
        $uploaded = UploadedFile::fake()->createWithContent('modpack.zip', $zipBytes);

        Filament::setCurrentPanel('admin');
        Livewire::test(CreateRelease::class)
            ->fillForm([
                'server_id' => $server->id,
                'version_label' => '1.0.0',
                'source_zip' => $uploaded,
            ])
            ->call('create')
            ->assertHasNoActionErrors();

        $release = Release::query()->latest()->first();
        $this->assertInstanceOf(Release::class, $release);

        Livewire::test(EditRelease::class, ['record' => $release->getKey()])
            ->callAction('prepare')
            ->assertHasNoActionErrors();

        $preparedPath = $release->refresh()->prepared_path;
        $this->assertIsString($preparedPath);
        $this->assertSame(
            ['feature' => ['enabled' => true]],
            json_decode((string) Storage::disk('local')->get($preparedPath.'/config/game.json'), true),
        );
        $this->assertSame('BINARYJAR', Storage::disk('local')->get($preparedPath.'/mods/example.jar'));
    }
}
