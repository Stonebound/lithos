<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Models\Server;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class FtbProvider implements ProviderInterface
{
    protected function get(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);

        return $body === false ? '' : $body;
    }

    public function listVersions(Server $server): array
    {
        if (! $server->provider_pack_id) {
            return [];
        }
        $body = $this->get('https://api.modpacks.ch/public/modpack/'.$server->provider_pack_id);
        $json = json_decode($body, true) ?? [];
        $versions = $json['versions'] ?? [];

        $out = [];
        foreach ($versions as $ver) {
            $out[] = [
                'id' => $ver['id'] ?? $ver['version'] ?? null,
                'name' => $ver['name'] ?? $ver['version'] ?? 'unknown',
            ];
        }

        return $out;
    }

    public function fetchSource(Server $server, $versionId): array
    {
        // Try to find a linux server installer URL.
        $manifestBody = $this->get('https://api.modpacks.ch/public/modpack/'.$server->provider_pack_id.'/'.$versionId);
        $manifest = json_decode($manifestBody, true) ?? [];

        $linuxUrl = $manifest['server']['linux'] ?? null;
        if (! $linuxUrl) {
            // Some manifests nest under 'targets' or similar; fall back to 'server'->'install'->'linux'
            $linuxUrl = $manifest['server']['install']['linux'] ?? null;
        }
        if (! $linuxUrl || ! is_string($linuxUrl)) {
            throw new \RuntimeException('FTB linux server installer URL not found in manifest.');
        }

        $installerContents = $this->get($linuxUrl);
        $relative = Storage::put('uploads', $installerContents);
        $installerPath = Storage::path($relative);

        // Prepare target directory
        $targetDir = storage_path('app/tmp/ftb/'.uniqid('ftb_', true));
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // Determine how to run installer
        $cmd = null;
        if (str_ends_with($installerPath, '.jar')) {
            $cmd = ['java', '-jar', $installerPath];
        } elseif (preg_match('/\.(sh|run)$/', $installerPath)) {
            @chmod($installerPath, 0755);
            $cmd = [$installerPath];
        } else {
            // Still try as binary
            @chmod($installerPath, 0755);
            $cmd = [$installerPath];
        }

        $process = new Process($cmd, $targetDir, null, null, 300);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new \RuntimeException('FTB installer failed: '.$process->getErrorOutput());
        }

        return ['type' => 'directory', 'path' => $targetDir];
    }
}
