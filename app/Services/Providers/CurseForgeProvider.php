<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Concerns\NormalizesRecordLists;
use App\Concerns\NormalizesStringValues;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CurseForgeProvider implements ProviderInterface
{
    use NormalizesRecordLists;
    use NormalizesStringValues;

    protected function apiKey(): ?string
    {
        $apiKey = Config::string('services.curseforge.key', '');

        return $apiKey !== '' ? $apiKey : null;
    }

    protected function get(string $url, array $headers = []): string
    {
        /** @var Response $response */
        $response = Http::withHeaders($headers)->timeout(30)->get($url);
        if ($response->status() >= 400) {
            throw new RuntimeException('HTTP GET failed: '.$url.' (status '.$response->status().')');
        }

        return (string) $response->body();
    }

    public function listVersions(string|int $providerPackId): array
    {
        if (! $providerPackId || ! $this->apiKey()) {
            return [];
        }

        /** @var Response $response */
        $response = Http::withHeaders(['x-api-key' => (string) $this->apiKey()])
            ->timeout(30)
            ->get('https://api.curseforge.com/v1/mods/'.$providerPackId.'/files');
        if ($response->status() >= 400) {
            throw new RuntimeException('CurseForge listVersions failed (status '.$response->status().')');
        }
        $data = self::normalizeRecordList($response->json('data'));
        /** @var array<int, array{id: int|string, name: string}> $versions */
        $versions = [];

        foreach ($data as $file) {
            $versions[] = [
                'id' => $this->requireIntOrString($file['id'] ?? null, 'CurseForge file id'),
                'name' => self::normalizeStringValue($file['displayName'] ?? $file['fileName'] ?? $file['id'] ?? 'unknown', 'unknown'),
            ];
        }

        return $versions;
    }

    public function fetchSource(string|int $providerPackId, string|int $versionId): array
    {
        if (! $this->apiKey()) {
            throw new RuntimeException('CurseForge API key not configured.');
        }

        /** @var Response $fileResponse */
        $fileResponse = Http::withHeaders(['x-api-key' => (string) $this->apiKey()])
            ->timeout(30)
            ->get('https://api.curseforge.com/v1/mods/'.$providerPackId.'/files/'.$versionId);
        if ($fileResponse->status() >= 400) {
            throw new RuntimeException('CurseForge file metadata failed (status '.$fileResponse->status().')');
        }
        $fileData = $this->responseRecord($fileResponse->json('data'));

        $serverPackId = $fileData['serverPackFileId'] ?? null;
        $downloadUrl = $this->nullableStringValue($fileData['downloadUrl'] ?? null);

        // Prefer the server pack additional file when available
        if ($serverPackId) {
            /** @var Response $serverPackResp */
            $serverPackResp = Http::withHeaders(['x-api-key' => (string) $this->apiKey()])
                ->timeout(30)
                ->get('https://api.curseforge.com/v1/mods/'.$providerPackId.'/files/'.$this->requireIntOrString($serverPackId, 'CurseForge server pack file id'));
            if ($serverPackResp->status() >= 400) {
                throw new RuntimeException('CurseForge server pack metadata failed (status '.$serverPackResp->status().')');
            }
            $downloadUrl = $this->nullableStringValue($serverPackResp->json('data.downloadUrl')) ?? $downloadUrl;
        }
        if (! $downloadUrl) {
            throw new RuntimeException('Download URL missing for CurseForge file.');
        }
        /** @var Response $download */
        $download = Http::timeout(120)->get($downloadUrl);
        if ($download->status() >= 400) {
            throw new RuntimeException('CurseForge download failed (status '.$download->status().')');
        }
        $contents = (string) $download->body();
        $relativePath = 'tmp/'.uniqid('cf_', true).'.zip';
        Storage::disk('local')->makeDirectory('tmp');
        Storage::disk('local')->put($relativePath, $contents);
        $path = Storage::disk('local')->path($relativePath);

        return ['type' => 'zip', 'path' => $path];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseRecord(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    private function requireIntOrString(mixed $value, string $label): int|string
    {
        if (is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        throw new RuntimeException($label.' is missing or invalid.');
    }

    private function nullableStringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::normalizeStringValue($value, 'unknown');
    }
}
