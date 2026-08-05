<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\CleanupOldReleases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupOldReleasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_delete_recently_prepared_release_files(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // Simulate a release that was just prepared moments ago.
        $disk->put('modpacks/1/prepared/config/game.json', '{}');
        $disk->put('modpacks/1/prepared/mods/example.jar', 'BINARYJAR');

        (new CleanupOldReleases)->handle();

        $this->assertTrue($disk->exists('modpacks/1/prepared/config/game.json'));
        $this->assertTrue($disk->exists('modpacks/1/prepared/mods/example.jar'));
    }

    public function test_it_deletes_stray_top_level_files_older_than_cutoff(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        $disk->put('modpacks/stray-old.zip', 'old');
        $disk->put('modpacks/stray-new.zip', 'new');

        touch($disk->path('modpacks/stray-old.zip'), now()->subDays(10)->getTimestamp());
        touch($disk->path('modpacks/stray-new.zip'), now()->getTimestamp());

        (new CleanupOldReleases)->handle();

        $this->assertFalse($disk->exists('modpacks/stray-old.zip'));
        $this->assertTrue($disk->exists('modpacks/stray-new.zip'));
    }

    public function test_it_does_not_recursively_wipe_nested_files_when_cleaning_stray_top_level_files(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        // A recent nested file must survive the top-level stray-file cleanup,
        // which must stay non-recursive rather than walking the whole tree.
        $disk->put('modpacks/2/prepared/config/game.json', '{}');

        (new CleanupOldReleases)->handle();

        $this->assertTrue($disk->exists('modpacks/2/prepared/config/game.json'));
    }

    public function test_it_deletes_release_directories_whose_files_are_all_older_than_cutoff(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        $disk->put('modpacks/3/prepared/config/game.json', '{}');
        touch($disk->path('modpacks/3/prepared/config/game.json'), now()->subDays(10)->getTimestamp());

        (new CleanupOldReleases)->handle();

        $this->assertFalse($disk->exists('modpacks/3'));
    }
}
