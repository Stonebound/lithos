<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\ReleaseStatus;
use App\Jobs\PrepareBackupZip;
use App\Models\Release;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DownloadBackupZipActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function action_is_visible_and_dispatches_job(): void
    {
        Bus::fake();
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);
        $server = Server::factory()->create();
        $release = Release::factory()->create([
            'server_id' => $server->id,
            'status' => ReleaseStatus::Prepared,
        ]);

        // Simulate remote snapshot folder exists
        Storage::disk('local')->makeDirectory("modpacks/{$release->id}/remote");
        Storage::disk('local')->put("modpacks/{$release->id}/remote/foo.txt", 'bar');

        Livewire::test(\App\Filament\Resources\Releases\Pages\EditRelease::class, ['record' => $release->id])
            ->assertActionVisible('download-backup-zip')
            ->callAction('download-backup-zip')
            ->assertHasNoActionErrors();

        Bus::assertDispatched(PrepareBackupZip::class, function ($job) use ($release, $user) {
            return $job->releaseId === $release->id && $job->userId === $user->id;
        });
    }

    #[Test]
    public function action_provides_download_when_zip_exists(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);
        $server = Server::factory()->create();
        $release = Release::factory()->create([
            'server_id' => $server->id,
            'status' => ReleaseStatus::Prepared,
        ]);
        $zipPath = "modpacks/{$release->id}/remote_snapshot.zip";
        Storage::disk('local')->put($zipPath, 'zipdata');

        Livewire::test(\App\Filament\Resources\Releases\Pages\EditRelease::class, ['record' => $release->id])
            ->assertActionVisible('download-backup-zip')
            ->callAction('download-backup-zip')
            ->assertHasNoActionErrors();
    }
}
