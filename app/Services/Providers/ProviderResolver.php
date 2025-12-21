<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Models\Server;

class ProviderResolver
{
    public function for(Server $server): ?ProviderInterface
    {
        return match ($server->provider) {
            'curseforge' => new CurseForgeProvider,
            'ftb' => new FtbProvider,
            default => null,
        };
    }
}
