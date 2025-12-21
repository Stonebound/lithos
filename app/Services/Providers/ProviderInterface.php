<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Models\Server;

interface ProviderInterface
{
    /**
     * Return available versions for the given server's pack.
     * Each item: ['id' => string|int, 'name' => string]
     *
     * @return array<int, array{id: string|int, name: string}>
     */
    public function listVersions(Server $server): array;

    /**
     * Fetch the source for the selected version.
     * Returns ['type' => 'zip'|'directory', 'path' => string]
     * where path is a local filesystem path.
     *
     * @param  string|int  $versionId
     * @return array{type: 'zip'|'directory', path: string}
     */
    public function fetchSource(Server $server, $versionId): array;
}
