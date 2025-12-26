<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MinecraftVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class FetchMinecraftVersions implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::get('https://launchermeta.mojang.com/mc/game/version_manifest.json');

        if ($response->failed()) {
            return;
        }

        $versions = $response->json('versions');

        foreach ($versions as $version) {
            if ($version['type'] !== 'release') {
                continue;
            }

            MinecraftVersion::updateOrCreate(
                ['id' => $version['id']],
                ['release_time' => $version['releaseTime']]
            );
        }
    }
}
