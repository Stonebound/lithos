<?php

declare(strict_types=1);

namespace App\Services\Dns;

use App\Models\SrvRecord;
use GuzzleHttp\Client;
use RuntimeException;
use ToshY\BunnyNet\BunnyHttpClient;
use ToshY\BunnyNet\Enum\Endpoint;
use ToshY\BunnyNet\Model\Api\Core\DnsZone\AddDnsRecord;
use ToshY\BunnyNet\Model\Api\Core\DnsZone\DeleteDnsRecord;
use ToshY\BunnyNet\Model\Api\Core\DnsZone\GetDnsZone;
use ToshY\BunnyNet\Model\Api\Core\DnsZone\ListDnsZones;
use ToshY\BunnyNet\Model\Api\Core\DnsZone\UpdateDnsRecord;

class BunnySrvDnsProvider implements SrvDnsProvider
{
    private readonly BunnyHttpClient $httpClient;

    public function __construct(?Client $client = null)
    {
        $this->httpClient = new BunnyHttpClient(
            client: $client ?? app(Client::class),
            apiKey: (string) config('services.bunnynet.api_key'),
            baseUrl: Endpoint::BASE,
        );
    }

    public function name(): string
    {
        return 'bunny';
    }

    public function createRecords(SrvRecord $srvRecord): array
    {
        $zoneId = $this->getZoneId();
        $recordIds = [];

        $recordIds[] = $this->addRecord($zoneId, $srvRecord, $srvRecord->subdomain, (string) config('services.dns.base_target'));

        foreach (config('services.dns.additional_subdomains', []) as $prefix) {
            $recordIds[] = $this->addRecord(
                $zoneId,
                $srvRecord,
                $srvRecord->subdomain,
                (string) (config("services.dns.additional_targets.{$prefix}") ?? config('services.dns.base_target')),
                (string) $prefix,
            );
        }

        return $recordIds;
    }

    public function findMatchingRecordIds(SrvRecord $srvRecord): array
    {
        $records = $this->getZoneRecords();
        $matches = [];
        $usedIndexes = [];

        foreach ($this->expectedRecords($srvRecord) as $expectedRecord) {
            $matchedIndex = null;

            foreach ($records as $index => $record) {
                if (in_array($index, $usedIndexes, true)) {
                    continue;
                }

                if (! $this->recordMatches($record, $expectedRecord)) {
                    continue;
                }

                $matchedIndex = $index;
                $usedIndexes[] = $index;
                $matches[] = (int) $record['Id'];

                break;
            }

            if ($matchedIndex === null) {
                return [];
            }
        }

        return $matches;
    }

    public function updateRecords(SrvRecord $srvRecord): void
    {
        if (empty($srvRecord->record_ids)) {
            return;
        }

        $zoneId = $this->getZoneId();

        foreach ($srvRecord->record_ids as $recordId) {
            $model = new UpdateDnsRecord($zoneId, (int) $recordId, [
                'Port' => $srvRecord->port,
            ]);
            $this->httpClient->request($model);
        }
    }

    public function deleteRecords(SrvRecord $srvRecord): void
    {
        if (empty($srvRecord->record_ids)) {
            return;
        }

        $zoneId = $this->getZoneId();

        foreach ($srvRecord->record_ids as $recordId) {
            $model = new DeleteDnsRecord($zoneId, (int) $recordId);
            $this->httpClient->request($model);
        }
    }

    private function getZoneId(): int
    {
        $domain = (string) config('services.dns.base_domain');

        $model = new ListDnsZones([
            'page' => 1,
            'perPage' => 10,
            'search' => $domain,
        ]);
        $response = $this->httpClient->request($model);
        $zones = $response->getContents()['Items'] ?? [];

        foreach ($zones as $zone) {
            if (($zone['Domain'] ?? null) === $domain) {
                return (int) $zone['Id'];
            }
        }

        throw new RuntimeException('DNS zone not found for domain: '.$domain);
    }

    private function addRecord(int $zoneId, SrvRecord $srvRecord, string $subdomain, string $target, string $prefix = ''): int
    {
        $name = '_minecraft._tcp.'.$subdomain.'.'.($prefix !== '' ? $prefix.'.' : '');

        $model = new AddDnsRecord($zoneId, [
            'Type' => 'SRV',
            'Name' => $name,
            'Value' => $target,
            'Port' => $srvRecord->port,
            'Priority' => 0,
            'Weight' => 5,
            'Ttl' => (int) config('services.dns.ttl'),
        ]);

        $response = $this->httpClient->request($model);

        return (int) $response->getContents()['Id'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getZoneRecords(): array
    {
        $response = $this->httpClient->request(new GetDnsZone($this->getZoneId()));

        return $response->getContents()['Records'] ?? [];
    }

    /**
     * @return array<int, array{name: string, value: string, port: int, priority: int, weight: int, ttl: int}>
     */
    private function expectedRecords(SrvRecord $srvRecord): array
    {
        $records = [[
            'name' => $this->normalizeName('_minecraft._tcp.'.$srvRecord->subdomain),
            'value' => $this->normalizeValue((string) config('services.dns.base_target')),
            'port' => $srvRecord->port,
            'priority' => 0,
            'weight' => 5,
            'ttl' => (int) config('services.dns.ttl'),
        ]];

        foreach (config('services.dns.additional_subdomains', []) as $prefix) {
            $records[] = [
                'name' => $this->normalizeName('_minecraft._tcp.'.$srvRecord->subdomain.'.'.(string) $prefix),
                'value' => $this->normalizeValue((string) (config("services.dns.additional_targets.{$prefix}") ?? config('services.dns.base_target'))),
                'port' => $srvRecord->port,
                'priority' => 0,
                'weight' => 5,
                'ttl' => (int) config('services.dns.ttl'),
            ];
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array{name: string, value: string, port: int, priority: int, weight: int, ttl: int}  $expectedRecord
     */
    private function recordMatches(array $record, array $expectedRecord): bool
    {
        return $this->normalizeName((string) ($record['Name'] ?? '')) === $expectedRecord['name']
            && $this->normalizeValue((string) ($record['Value'] ?? '')) === $expectedRecord['value']
            && (int) ($record['Port'] ?? 0) === $expectedRecord['port']
            && (int) ($record['Priority'] ?? 0) === $expectedRecord['priority']
            && (int) ($record['Weight'] ?? 0) === $expectedRecord['weight']
            && (int) ($record['Ttl'] ?? 0) === $expectedRecord['ttl'];
    }

    private function normalizeName(string $name): string
    {
        return rtrim($name, '.');
    }

    private function normalizeValue(string $value): string
    {
        return rtrim($value, '.');
    }
}
