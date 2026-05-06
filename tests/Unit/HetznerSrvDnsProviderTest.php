<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SrvRecord;
use App\Services\Dns\HetznerSrvDnsProvider;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use LKDev\HetznerCloud\Clients\GuzzleClient;
use LKDev\HetznerCloud\HetznerAPIClient;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HetznerSrvDnsProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_hetzner_srv_rrsets(): void
    {
        Config::set('services.dns.base_domain', 'example.com');
        Config::set('services.dns.base_target', 'mc.example.com');
        Config::set('services.dns.additional_subdomains', ['la']);
        Config::set('services.dns.additional_targets', ['la' => 'la.example.com']);
        Config::set('services.dns.ttl', 300);

        $provider = new HetznerSrvDnsProvider($this->mockHetznerClient([
            new Response(200, [], $this->zoneResponse()),
            new Response(201, [], $this->rrsetResponse('survival/SRV', '_minecraft._tcp.survival')),
            new Response(201, [], $this->rrsetResponse('survival.la/SRV', '_minecraft._tcp.survival.la')),
        ]));

        $srvRecord = SrvRecord::factory()->make(['subdomain' => 'survival', 'port' => 25565]);

        $this->assertSame(['survival/SRV', 'survival.la/SRV'], $provider->createRecords($srvRecord));
    }

    #[Test]
    public function it_updates_and_deletes_hetzner_srv_rrsets(): void
    {
        Config::set('services.dns.base_domain', 'example.com');
        Config::set('services.dns.base_target', 'mc.example.com');
        Config::set('services.dns.additional_subdomains', ['la']);
        Config::set('services.dns.additional_targets', ['la' => 'la.example.com']);
        Config::set('services.dns.ttl', 300);

        $provider = new HetznerSrvDnsProvider($this->mockHetznerClient([
            new Response(200, [], $this->zoneResponse()),
            new Response(200, [], $this->rrsetSingleResponse('survival/SRV', '_minecraft._tcp.survival', '0 5 25565 mc.example.com.')),
            new Response(200, [], $this->rrsetSingleResponse('survival/SRV', '_minecraft._tcp.survival', '0 5 25570 mc.example.com.')),
            new Response(200, [], $this->rrsetSingleResponse('survival.la/SRV', '_minecraft._tcp.survival.la', '0 5 25565 la.example.com.')),
            new Response(200, [], $this->rrsetSingleResponse('survival.la/SRV', '_minecraft._tcp.survival.la', '0 5 25570 la.example.com.')),
            new Response(200, [], $this->zoneResponse()),
            new Response(200, [], $this->rrsetSingleResponse('survival/SRV', '_minecraft._tcp.survival', '0 5 25570 mc.example.com.')),
            new Response(200, [], json_encode(['action' => ['id' => 1, 'command' => 'delete_rrset', 'status' => 'success', 'progress' => 100, 'started' => '2026-05-06T00:00:00Z', 'finished' => '2026-05-06T00:00:01Z', 'resources' => [['id' => 'survival/SRV', 'type' => 'rrset']]]], JSON_THROW_ON_ERROR)),
            new Response(200, [], $this->rrsetSingleResponse('survival.la/SRV', '_minecraft._tcp.survival.la', '0 5 25570 la.example.com.')),
            new Response(200, [], json_encode(['action' => ['id' => 1, 'command' => 'delete_rrset', 'status' => 'success', 'progress' => 100, 'started' => '2026-05-06T00:00:00Z', 'finished' => '2026-05-06T00:00:01Z', 'resources' => [['id' => 'survival.la/SRV', 'type' => 'rrset']]]], JSON_THROW_ON_ERROR)),
        ]));

        $srvRecord = SrvRecord::factory()->make(['subdomain' => 'survival', 'port' => 25570, 'record_ids' => ['survival/SRV', 'survival.la/SRV']]);

        $provider->updateRecords($srvRecord);
        $provider->deleteRecords($srvRecord);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_finds_matching_hetzner_srv_rrsets(): void
    {
        Config::set('services.dns.base_domain', 'example.com');
        Config::set('services.dns.base_target', 'mc.example.com');
        Config::set('services.dns.additional_subdomains', ['la']);
        Config::set('services.dns.additional_targets', ['la' => 'la.example.com']);
        Config::set('services.dns.ttl', 300);

        $provider = new HetznerSrvDnsProvider($this->mockHetznerClient([
            new Response(200, [], $this->zoneResponse()),
            new Response(200, [], $this->rrsetsListResponse([
                [
                    'id' => 'survival/SRV',
                    'name' => '_minecraft._tcp.survival',
                    'value' => '0 5 25565 mc.example.com.',
                ],
                [
                    'id' => 'survival.la/SRV',
                    'name' => '_minecraft._tcp.survival.la',
                    'value' => '0 5 25565 la.example.com.',
                ],
            ])),
        ]));

        $srvRecord = SrvRecord::factory()->make(['subdomain' => 'survival', 'port' => 25565]);

        $this->assertSame(['survival/SRV', 'survival.la/SRV'], $provider->findMatchingRecordIds($srvRecord));
    }

    /**
     * @param  array<int, Response>  $responses
     */
    private function mockHetznerClient(array $responses): HetznerAPIClient
    {
        $mock = new MockHandler($responses);
        $api = new HetznerAPIClient('token', 'https://api.hetzner.cloud/v1/');
        $api->setHttpClient(new GuzzleClient($api, ['handler' => $mock]));

        return $api;
    }

    private function zoneResponse(): string
    {
        return json_encode([
            'zone' => [
                'id' => 4711,
                'name' => 'example.com',
                'status' => 'verified',
                'created' => '2026-05-06T00:00:00Z',
                'mode' => 'dns',
                'ttl' => 300,
                'record_count' => 2,
                'registrar' => '',
                'labels' => (object) [],
                'protection' => ['delete' => false],
                'authoritative_nameservers' => [
                    'assigned' => ['hydrogen.ns.hetzner.com'],
                    'delegated' => ['hydrogen.ns.hetzner.com'],
                    'delegation_last_check' => '2026-05-06T00:00:00Z',
                    'delegation_status' => 'valid',
                ],
                'primary_nameservers' => [],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function rrsetResponse(string $id, string $name): string
    {
        return json_encode([
            'action' => [
                'id' => 1,
                'command' => 'create_rrset',
                'status' => 'success',
                'progress' => 100,
                'started' => '2026-05-06T00:00:00Z',
                'finished' => '2026-05-06T00:00:01Z',
                'resources' => [['id' => $id, 'type' => 'rrset']],
            ],
            'rrset' => [
                'id' => $id,
                'name' => $name,
                'type' => 'SRV',
                'ttl' => 300,
                'records' => [['value' => '0 5 25565 mc.example.com.', 'comment' => 'Managed by Lithos']],
                'labels' => (object) [],
                'protection' => (object) [],
                'zone' => 4711,
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function rrsetSingleResponse(string $id, string $name, string $value): string
    {
        return json_encode([
            'rrset' => [
                'id' => $id,
                'name' => $name,
                'type' => 'SRV',
                'ttl' => 300,
                'records' => [['value' => $value, 'comment' => 'Managed by Lithos']],
                'labels' => (object) [],
                'protection' => (object) [],
                'zone' => 4711,
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<int, array{id: string, name: string, value: string}>  $rrsets
     */
    private function rrsetsListResponse(array $rrsets): string
    {
        return json_encode([
            'meta' => [
                'pagination' => [
                    'page' => 1,
                    'per_page' => 200,
                    'previous_page' => null,
                    'next_page' => null,
                    'last_page' => 1,
                    'total_entries' => count($rrsets),
                ],
            ],
            'rrsets' => array_map(fn (array $rrset): array => [
                'id' => $rrset['id'],
                'name' => $rrset['name'],
                'type' => 'SRV',
                'ttl' => 300,
                'records' => [['value' => $rrset['value'], 'comment' => 'Managed by Lithos']],
                'labels' => (object) [],
                'protection' => (object) [],
                'zone' => 4711,
            ], $rrsets),
        ], JSON_THROW_ON_ERROR);
    }
}
