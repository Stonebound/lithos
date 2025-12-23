<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PterodactylService
{
    private ?string $baseUrl;

    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.pterodactyl.base_url');
        $this->apiKey = config('services.pterodactyl.api_key');
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
    public function isPterodactylServer(Model $server): bool
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
    public function extractServerId(Model $server): ?string
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
    public function getServerState(Model $server): ?string
    {
        $serverId = $this->extractServerId($server);
        if (! $serverId || ! $this->isGloballyConfigured()) {
            return null;
        }

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Accept' => 'Application/vnd.pterodactyl.v1+json',
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/api/client/servers/{$serverId}/resources");

            if ($response->successful()) {
                return $response->json('attributes.current_state');
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
    public function stopServerIfRunning(Model $server): bool
    {
        $serverId = $this->extractServerId($server);
        if (! $serverId || ! $this->isGloballyConfigured()) {
            return true; // Not configured, so we can proceed
        }

        $state = $this->getServerState($server);

        if ($state === 'running') {
            Log::info('Server is running, stopping before deployment', ['server_id' => $serverId]);

            try {
                /** @var \Illuminate\Http\Client\Response $response */
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
     * Wait for the server to reach a specific state.
     */
    private function waitForServerState(Model $server, string $targetState, int $maxWaitSeconds = 60): bool
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
