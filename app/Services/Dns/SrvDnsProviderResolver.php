<?php

declare(strict_types=1);

namespace App\Services\Dns;

use GuzzleHttp\Client;
use InvalidArgumentException;

class SrvDnsProviderResolver
{
    public function providerName(): string
    {
        $provider = config('services.dns.provider');

        if (! is_string($provider)) {
            throw new InvalidArgumentException('Unsupported DNS provider configured.');
        }

        return match ($provider) {
            'bunny' => 'bunny',
            'hetzner' => 'hetzner',
            default => throw new InvalidArgumentException('Unsupported DNS provider configured.'),
        };
    }

    public function resolve(): SrvDnsProvider
    {
        return match ($this->providerName()) {
            'bunny' => new BunnySrvDnsProvider(app(Client::class)),
            'hetzner' => new HetznerSrvDnsProvider,
            default => throw new InvalidArgumentException('Unsupported DNS provider configured.'),
        };
    }

    public function isConfigured(): bool
    {
        return match ($this->providerName()) {
            'bunny' => filled(config('services.bunnynet.api_key')),
            'hetzner' => filled(config('services.hetzner.api_token')),
            default => false,
        };
    }
}
