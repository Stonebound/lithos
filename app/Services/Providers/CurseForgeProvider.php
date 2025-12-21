<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class CurseForgeProvider implements ProviderInterface
{
    protected function apiKey(): ?string
    {
        return config('services.curseforge.key');
    }

    protected function get(string $url, array $headers = []): string
    {
        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withHeaders($headers)->timeout(30)->get($url);
        if ($response->status() >= 400) {
            throw new \RuntimeException('HTTP GET failed: '.$url.' (status '.$response->status().')');
        }

        return (string) $response->body();
    }

    public function listVersions(string|int $providerPackId): array
    {
        if (! $providerPackId || ! $this->apiKey()) {
            return [];
        }

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withHeaders(['x-api-key' => (string) $this->apiKey()])
            ->timeout(30)
            ->get('https://api.curseforge.com/v1/mods/'.$providerPackId.'/files');
        if ($response->status() >= 400) {
            throw new \RuntimeException('CurseForge listVersions failed (status '.$response->status().')');
        }
        $data = $response->json('data') ?? [];
        $versions = [];
        foreach ($data as $file) {
            $versions[] = [
                'id' => $file['id'],
                'name' => $file['displayName'] ?? ($file['fileName'] ?? (string) $file['id']),
            ];
        }

        return $versions;
    }

    public function fetchSource($providerPackId, $versionId): array
    {
        if (! $this->apiKey()) {
            throw new \RuntimeException('CurseForge API key not configured.');
        }

        /** @var \Illuminate\Http\Client\Response $fileResponse */
        $fileResponse = Http::withHeaders(['x-api-key' => (string) $this->apiKey()])
            ->timeout(30)
            ->get('https://api.curseforge.com/v1/mods/'.$providerPackId.'/files/'.$versionId);
        if ($fileResponse->status() >= 400) {
            throw new \RuntimeException('CurseForge file metadata failed (status '.$fileResponse->status().')');
        }
        $fileData = $fileResponse->json('data') ?? [];

        dd($fileData);
        $serverPackId = $fileData['serverPackFileId'] ?? null;
        $downloadUrl = $fileData['downloadUrl'] ?? null;

        // Prefer the server pack additional file when available
        if ($serverPackId) {
            /** @var \Illuminate\Http\Client\Response $serverPackResp */
            $serverPackResp = Http::withHeaders(['x-api-key' => (string) $this->apiKey()])
                ->timeout(30)
                ->get('https://api.curseforge.com/v1/mods/'.$providerPackId.'/files/'.$serverPackId);
            if ($serverPackResp->status() >= 400) {
                throw new \RuntimeException('CurseForge server pack metadata failed (status '.$serverPackResp->status().')');
            }
            $downloadUrl = $serverPackResp->json('data.downloadUrl') ?? $downloadUrl;
        }
        if (! $downloadUrl) {
            throw new \RuntimeException('Download URL missing for CurseForge file.');
        }
        /** @var \Illuminate\Http\Client\Response $download */
        $download = Http::timeout(120)->get($downloadUrl);
        if ($download->status() >= 400) {
            throw new \RuntimeException('CurseForge download failed (status '.$download->status().')');
        }
        $contents = (string) $download->body();
        $relativePath = 'uploads/'.uniqid('cf_', true).'.zip';
        Storage::disk('local')->makeDirectory('uploads');
        Storage::disk('local')->put($relativePath, $contents);
        $path = Storage::disk('local')->path($relativePath);

        return ['type' => 'zip', 'path' => $path];
    }
}
