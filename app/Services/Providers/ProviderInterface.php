<?php

declare(strict_types=1);

namespace App\Services\Providers;

interface ProviderInterface
{
    /**
     * Return available versions for the given provider pack id.
     * Each item: ['id' => string|int, 'name' => string]
     *
     * @return array<int, array{id: string|int, name: string}>
     */
    public function listVersions(string|int $providerPackId): array;

    /**
     * Fetch the source for the selected version.
     * Returns ['type' => 'zip'|'dir', 'path' => string]
     * where path is a local filesystem path.
     *
     * @param  string|int  $providerPackId
     * @param  string|int  $versionId
     * @return array{type: 'zip'|'dir', path: string}
     */
    public function fetchSource($providerPackId, $versionId): array;
}
