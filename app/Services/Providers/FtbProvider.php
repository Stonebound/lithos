<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Concerns\NormalizesRecordLists;
use App\Concerns\NormalizesStringValues;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class FtbProvider implements ProviderInterface
{
    use NormalizesRecordLists;
    use NormalizesStringValues;

    protected function get(string $url): string
    {
        /** @var Response $response */
        $response = Http::timeout(30)->get($url);
        if ($response->status() >= 400) {
            throw new RuntimeException('HTTP GET failed: '.$url.' (status '.$response->status().')');
        }

        return (string) $response->body();
    }

    public function listVersions(string|int $providerPackId): array
    {
        if (! $providerPackId) {
            return [];
        }
        $body = $this->get('https://api.feed-the-beast.com/v1/modpacks/public/modpack/'.$providerPackId);
        $json = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        $payload = is_array($json) ? $json : [];
        $versions = self::normalizeRecordList($payload['versions'] ?? []);

        /** @var array<int, array{id: int|string, name: string}> $out */
        $out = [];
        foreach ($versions as $ver) {
            $out[] = [
                'id' => $this->requireIntOrString($ver['id'] ?? $ver['version'] ?? null, 'FTB version id'),
                'name' => self::normalizeStringValue($ver['name'] ?? $ver['version'] ?? 'unknown', 'unknown'),
            ];
        }

        // revert order to have newest versions first
        $out = array_reverse($out);

        return $out;
    }

    public function fetchSource(string|int $providerPackId, string|int $versionId): array
    {
        if (! $providerPackId || ! $versionId) {
            throw new RuntimeException('FTB pack id or version id is missing.');
        }

        $isArm64 = (stripos(php_uname('m'), 'aarch64') !== false || stripos(php_uname('m'), 'arm64') !== false);

        // Static Linux server installer URL per FTB API.
        $linuxUrl = sprintf(
            'https://api.feed-the-beast.com/v1/modpacks/public/modpack/%s/%s/server/%slinux',
            $providerPackId,
            $versionId,
            $isArm64 ? 'arm64/' : ''
        );

        $installerContents = $this->get($linuxUrl);
        Storage::disk('local')->makeDirectory('tmp');
        // Name must include pack and version to drive installer behavior.
        $relativePath = 'tmp/'.sprintf('serverinstall_%s_%s', $providerPackId, $versionId);
        Storage::disk('local')->put($relativePath, $installerContents);
        $installerPath = Storage::disk('local')->path($relativePath);

        // Prepare target directory
        $targetDir = 'tmp/ftb/'.uniqid('ftb_', true);
        Storage::disk('local')->makeDirectory($targetDir);
        $absTargetDir = Storage::disk('local')->path($targetDir);

        // Determine how to run installer and pass non-interactive flags (installer is a binary, not a JAR)
        $packId = (int) $providerPackId;
        $verId = (int) $versionId;
        @chmod($installerPath, 0755);
        $cmd = [
            $installerPath,
            '-auto', '-accept-eula',
            '-dir', $absTargetDir,
            '-pack', (string) $packId,
            '-version', (string) $verId,
            '-provider', 'ftb',
            '-apikey', 'public',
            '-just-files', '-no-java',
        ];

        $process = new Process($cmd, $absTargetDir, null, null, 300);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('FTB installer failed: '.$process->getErrorOutput());
        }

        return ['type' => 'dir', 'path' => $targetDir];
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
}
