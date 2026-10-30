<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Server;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PterodactylService
{
    private ?string $baseUrl;

    private ?string $apiKey;

    public function __construct()
    {
        $baseUrl = Config::get('services.pterodactyl.base_url');
        $apiKey = Config::get('services.pterodactyl.api_key');

        $this->baseUrl = is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : null;
        $this->apiKey = is_string($apiKey) && $apiKey !== '' ? $apiKey : null;
    }

    /**
     * Check if Pterodactyl integration is configured globally.
     */
    public function isGloballyConfigured(): bool
    {
        return ! empty($this->baseUrl) && ! empty($this->apiKey);
    }

    /**
     * Check if a server is a Pterodactyl server based on username pattern.
     */
    public function isPterodactylServer(Server $server): bool
    {
        if (! $this->isGloballyConfigured()) {
            return false;
        }

        // Check if username matches pattern: user.{uuid}
        return preg_match('/^user\.([a-f0-9-]{8,})/', $server->username, $matches) === 1;
    }

    /**
     * Extract server ID from username pattern.
     */
    public function extractServerId(Server $server): ?string
    {
        if (! $this->isPterodactylServer($server)) {
            return null;
        }

        if (preg_match('/^user\.([a-f0-9-]{8,})/', $server->username, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get the current server state.
     */
    public function getServerState(Server $server): ?string
    {
        $serverId = $this->extractServerId($server);
        if (! $serverId || ! $this->isGloballyConfigured()) {
            return null;
        }

        try {
            /** @var Response $response */
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Accept' => 'Application/vnd.pterodactyl.v1+json',
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/api/client/servers/{$serverId}/resources");

            if ($response->successful()) {
                $state = $response->json('attributes.current_state');

                return is_string($state) ? $state : null;
            }

            Log::warning('Failed to get Pterodactyl server state', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception getting Pterodactyl server state', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Stop the server if it's running.
     */
    public function stopServerIfRunning(Server $server): bool
    {
        $serverId = $this->extractServerId($server);
        if (! $serverId || ! $this->isGloballyConfigured()) {
            return true; // Not configured, so we can proceed
        }

        $state = $this->getServerState($server);

        if ($state === 'running') {
            Log::info('Server is running, stopping before deployment', ['server_id' => $serverId]);

            try {
                /** @var Response $response */
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Accept' => 'Application/vnd.pterodactyl.v1+json',
                    'Content-Type' => 'application/json',
                ])->post("{$this->baseUrl}/api/client/servers/{$serverId}/power", [
                    'signal' => 'stop',
                ]);

                if ($response->successful()) {
                    Log::info('Server stop command sent successfully', ['server_id' => $serverId]);

                    // Wait for server to stop
                    return $this->waitForServerState($server, 'offline');
                }

                Log::warning('Failed to stop Pterodactyl server', [
                    'server_id' => $serverId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            } catch (\Exception $e) {
                Log::error('Exception stopping Pterodactyl server', [
                    'server_id' => $serverId,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        }

        if ($state === 'offline' || $state === 'stopping') {
            Log::info('Server is already stopped or stopping', ['server_id' => $serverId]);

            return true;
        }

        Log::warning('Unknown server state, proceeding with deployment', [
            'server_id' => $serverId,
            'state' => $state,
        ]);

        return true;
    }

    /**
     * List the entries of a directory on the server.
     *
     * @return list<array{name: string, is_file: bool, is_symlink: bool}>
     *
     * @throws \RuntimeException
     */
    public function listFiles(Server $server, string $directory): array
    {
        $response = $this->fileRequest()->get($this->serverUrl($server, 'files/list'), [
            'directory' => $directory,
        ]);
        $this->ensureSuccessful($response, "list files in {$directory}");

        $entries = [];
        foreach ((array) $response->json('data', []) as $item) {
            $name = data_get($item, 'attributes.name');
            if (! is_string($name) || $name === '') {
                continue;
            }

            $entries[] = [
                'name' => $name,
                'is_file' => data_get($item, 'attributes.is_file') === true,
                'is_symlink' => data_get($item, 'attributes.is_symlink') === true,
            ];
        }

        return $entries;
    }

    /**
     * Compress the given paths (relative to $root) into a single tar.gz archive
     * written to $root on the server, and return the archive's file name.
     *
     * @param  list<string>  $files
     *
     * @throws \RuntimeException
     */
    public function compressFiles(Server $server, string $root, array $files): string
    {
        $response = $this->fileRequest()
            ->timeout(900)
            ->post($this->serverUrl($server, 'files/compress'), [
                'root' => $root,
                'files' => $files,
            ]);
        $this->ensureSuccessful($response, 'compress files');

        $name = $response->json('attributes.name');
        if (! is_string($name) || $name === '' || str_contains($name, '/')) {
            throw new \RuntimeException('Pterodactyl did not return a valid archive name.');
        }

        return $name;
    }

    /**
     * Get a short-lived signed URL to download a file from the server.
     *
     * @throws \RuntimeException
     */
    public function getDownloadUrl(Server $server, string $file): string
    {
        $response = $this->fileRequest()->get($this->serverUrl($server, 'files/download'), [
            'file' => $file,
        ]);
        $this->ensureSuccessful($response, "get download URL for {$file}");

        $url = $response->json('attributes.url');
        if (! is_string($url) || $url === '') {
            throw new \RuntimeException('Pterodactyl did not return a download URL.');
        }

        return $url;
    }

    /**
     * Delete the given files (relative to $root) on the server.
     *
     * @param  list<string>  $files
     *
     * @throws \RuntimeException
     */
    public function deleteFiles(Server $server, string $root, array $files): void
    {
        $response = $this->fileRequest()->post($this->serverUrl($server, 'files/delete'), [
            'root' => $root,
            'files' => $files,
        ]);
        $this->ensureSuccessful($response, 'delete files');
    }

    private function fileRequest(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Accept' => 'Application/vnd.pterodactyl.v1+json',
            'Content-Type' => 'application/json',
        ])->timeout(60);
    }

    /**
     * @throws \RuntimeException
     */
    private function serverUrl(Server $server, string $path): string
    {
        $serverId = $this->extractServerId($server);
        if (! $serverId) {
            throw new \RuntimeException('Server is not a configured Pterodactyl server.');
        }

        return "{$this->baseUrl}/api/client/servers/{$serverId}/{$path}";
    }

    /**
     * @throws \RuntimeException
     */
    private function ensureSuccessful(Response $response, string $action): void
    {
        if ($response->successful()) {
            return;
        }

        $error = $response->json('errors.0.detail') ?? $response->json('error');

        throw new \RuntimeException(sprintf(
            'Pterodactyl failed to %s (HTTP %d)%s',
            $action,
            $response->status(),
            is_string($error) && $error !== '' ? ': '.$error : '.',
        ));
    }

    /**
     * Wait for the server to reach a specific state.
     */
    private function waitForServerState(Server $server, string $targetState, int $maxWaitSeconds = 60): bool
    {
        $serverId = $this->extractServerId($server);
        $startTime = time();

        while (time() - $startTime < $maxWaitSeconds) {
            $currentState = $this->getServerState($server);

            if ($currentState === $targetState) {
                Log::info("Server reached target state: {$targetState}", ['server_id' => $serverId]);

                return true;
            }

            Log::info("Waiting for server state {$targetState}, current: {$currentState}", ['server_id' => $serverId]);
            sleep(5); // Wait 5 seconds before checking again
        }

        Log::warning("Server did not reach state {$targetState} within {$maxWaitSeconds} seconds", ['server_id' => $serverId]);

        return false;
    }
}
