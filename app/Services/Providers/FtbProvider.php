<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class FtbProvider implements ProviderInterface
{
    protected function get(string $url): string
    {
        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::timeout(30)->get($url);
        if ($response->status() >= 400) {
            throw new \RuntimeException('HTTP GET failed: '.$url.' (status '.$response->status().')');
        }

        return (string) $response->body();
    }

    public function listVersions(string|int $providerPackId): array
    {
        if (! $providerPackId) {
            return [];
        }
        $body = $this->get('https://api.feed-the-beast.com/v1/modpacks/public/modpack/'.$providerPackId);
        $json = json_decode($body, true) ?? [];
        $versions = $json['versions'] ?? [];

        $out = [];
        foreach ($versions as $ver) {
            $out[] = [
                'id' => $ver['id'] ?? $ver['version'] ?? null,
                'name' => $ver['name'] ?? $ver['version'] ?? 'unknown',
            ];
        }

        // revert order to have newest versions first
        $out = array_reverse($out);

        return $out;
    }

    public function fetchSource($providerPackId, $versionId): array
    {
        if (! $providerPackId || ! $versionId) {
            throw new \RuntimeException('FTB pack id or version id is missing.');
        }

        // Static Linux server installer URL per FTB API.
        $linuxUrl = sprintf(
            'https://api.feed-the-beast.com/v1/modpacks/public/modpack/%s/%s/server/linux',
            $providerPackId,
            $versionId
        );

        $installerContents = $this->get($linuxUrl);
        $absUploads = storage_path('uploads');
        Storage::disk('local')->makeDirectory('uploads');
        // Name must include pack and version to drive installer behavior.
        $relativePath = 'uploads/'.sprintf('serverinstall_%s_%s', $providerPackId, $versionId);
        Storage::disk('local')->put($relativePath, $installerContents);
        $installerPath = Storage::disk('local')->path($relativePath);

        // Prepare target directory
        $targetDir = 'tmp/ftb/'.uniqid('ftb_', true);
        Storage::disk('local')->makeDirectory($targetDir);

        // Determine how to run installer and pass non-interactive flags (installer is a binary, not a JAR)
        $packId = (int) $providerPackId;
        $verId = (int) $versionId;
        @chmod($installerPath, 0755);
        $cmd = [
            $installerPath,
            '-auto', '-accept-eula',
            '-dir', $targetDir,
            '-pack', (string) $packId,
            '-version', (string) $verId,
            '-provider', 'ftb',
            '-apikey', 'public',
            '-just-files', '-no-java',
        ];

        $process = new Process($cmd, $targetDir, null, null, 300);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new \RuntimeException('FTB installer failed: '.$process->getErrorOutput());
        }

        return ['type' => 'directory', 'path' => $targetDir];
    }
}
