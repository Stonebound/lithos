<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Models\Server;
use Illuminate\Support\Facades\Storage;

class CurseForgeProvider implements ProviderInterface
{
    protected function apiKey(): ?string
    {
        return config('services.curseforge.key');
    }

    protected function get(string $url, array $headers = []): string
    {
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k.': '.$v;
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headerLines),
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        return $body === false ? '' : $body;
    }

    public function listVersions(Server $server): array
    {
        if (! $server->provider_pack_id || ! $this->apiKey()) {
            return [];
        }

        $respBody = $this->get('https://api.curseforge.com/v1/mods/'.$server->provider_pack_id.'/files', [
            'x-api-key' => (string) $this->apiKey(),
        ]);

        $respJson = json_decode($respBody, true) ?? [];
        $data = $respJson['data'] ?? [];
        $versions = [];
        foreach ($data as $file) {
            $versions[] = [
                'id' => $file['id'],
                'name' => $file['displayName'] ?? ($file['fileName'] ?? (string) $file['id']),
            ];
        }

        return $versions;
    }

    public function fetchSource(Server $server, $versionId): array
    {
        if (! $this->apiKey()) {
            throw new \RuntimeException('CurseForge API key not configured.');
        }

        $fileBody = $this->get('https://api.curseforge.com/v1/mods/files/'.$versionId, [
            'x-api-key' => (string) $this->apiKey(),
        ]);
        $fileJson = json_decode($fileBody, true) ?? [];
        $downloadUrl = $fileJson['data']['downloadUrl'] ?? null;
        if (! $downloadUrl) {
            throw new \RuntimeException('Download URL missing for CurseForge file.');
        }

        $contents = $this->get($downloadUrl);
        $relative = Storage::put('uploads', $contents);
        $path = Storage::path($relative);

        return ['type' => 'zip', 'path' => $path];
    }
}
