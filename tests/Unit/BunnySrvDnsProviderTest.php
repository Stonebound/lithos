<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SrvRecord;
use App\Services\Dns\BunnySrvDnsProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BunnySrvDnsProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_bunny_srv_records(): void
    {
        Config::set('services.dns.base_domain', 'example.com');
        Config::set('services.dns.base_target', 'mc.example.com');
        Config::set('services.dns.additional_subdomains', ['la']);
        Config::set('services.dns.additional_targets', ['la' => 'la.example.com']);
        Config::set('services.dns.ttl', 300);
        Config::set('services.bunnynet.api_key', 'token');

        $provider = new BunnySrvDnsProvider($this->mockBunnyClient([
            new Response(200, [], json_encode(['Items' => [['Id' => 123, 'Domain' => 'example.com']]], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['Id' => 456], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['Id' => 457], JSON_THROW_ON_ERROR)),
        ]));

        $srvRecord = SrvRecord::factory()->make(['subdomain' => 'survival', 'port' => 25565]);

        $this->assertSame([456, 457], $provider->createRecords($srvRecord));
    }

    #[Test]
    public function it_updates_and_deletes_bunny_srv_records(): void
    {
        Config::set('services.dns.base_domain', 'example.com');
        Config::set('services.bunnynet.api_key', 'token');

        $provider = new BunnySrvDnsProvider($this->mockBunnyClient([
            new Response(200, [], json_encode(['Items' => [['Id' => 123, 'Domain' => 'example.com']]], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['success' => true], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['success' => true], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['Items' => [['Id' => 123, 'Domain' => 'example.com']]], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['success' => true], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['success' => true], JSON_THROW_ON_ERROR)),
        ]));

        $srvRecord = SrvRecord::factory()->make(['port' => 25570, 'record_ids' => [1, 2]]);

        $provider->updateRecords($srvRecord);
        $provider->deleteRecords($srvRecord);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_finds_matching_bunny_srv_records(): void
    {
        Config::set('services.dns.base_domain', 'example.com');
        Config::set('services.dns.base_target', 'mc.example.com');
        Config::set('services.dns.additional_subdomains', ['la']);
        Config::set('services.dns.additional_targets', ['la' => 'la.example.com']);
        Config::set('services.dns.ttl', 300);
        Config::set('services.bunnynet.api_key', 'token');

        $provider = new BunnySrvDnsProvider($this->mockBunnyClient([
            new Response(200, [], json_encode(['Items' => [['Id' => 123, 'Domain' => 'example.com']]], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'Id' => 123,
                'Domain' => 'example.com',
                'Records' => [
                    [
                        'Id' => 456,
                        'Ttl' => 300,
                        'Value' => 'mc.example.com',
                        'Name' => '_minecraft._tcp.survival.',
                        'Weight' => 5,
                        'Priority' => 0,
                        'Port' => 25565,
                    ],
                    [
                        'Id' => 457,
                        'Ttl' => 300,
                        'Value' => 'la.example.com',
                        'Name' => '_minecraft._tcp.survival.la.',
                        'Weight' => 5,
                        'Priority' => 0,
                        'Port' => 25565,
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]));

        $srvRecord = SrvRecord::factory()->make(['subdomain' => 'survival', 'port' => 25565]);

        $this->assertSame([456, 457], $provider->findMatchingRecordIds($srvRecord));
    }

    /**
     * @param  array<int, Response>  $responses
     */
    private function mockBunnyClient(array $responses): Client
    {
        $mock = new MockHandler($responses);

        return new Client(['handler' => HandlerStack::create($mock)]);
    }
}
