<?php

declare(strict_types=1);

namespace App\Services\Dns;

use App\Concerns\NormalizesRecordLists;
use App\Concerns\NormalizesStringValues;
use App\Models\SrvRecord;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
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
    use NormalizesRecordLists;
    use NormalizesStringValues;

    private readonly BunnyHttpClient $httpClient;

    public function __construct(?Client $client = null)
    {
        $this->httpClient = new BunnyHttpClient(
            client: $client ?? app(Client::class),
            apiKey: $this->apiKey(),
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
        /** @var array<int, int> $recordIds */
        $recordIds = [];

        $recordIds[] = $this->addRecord($zoneId, $srvRecord, $srvRecord->subdomain, $this->baseTarget());

        foreach ($this->additionalSubdomains() as $prefix) {
            $recordIds[] = $this->addRecord(
                $zoneId,
                $srvRecord,
                $srvRecord->subdomain,
                $this->additionalTarget($prefix),
                $prefix,
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
                $matches[] = $this->requireInt($record['Id'] ?? null, 'Bunny DNS record id');

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
            if (! is_int($recordId) && ! is_string($recordId) && ! is_numeric($recordId)) {
                continue;
            }

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
            if (! is_int($recordId) && ! is_string($recordId) && ! is_numeric($recordId)) {
                continue;
            }

            $model = new DeleteDnsRecord($zoneId, (int) $recordId);
            $this->httpClient->request($model);
        }
    }

    private function getZoneId(): int
    {
        $domain = $this->baseDomain();

        $model = new ListDnsZones([
            'page' => 1,
            'perPage' => 10,
            'search' => $domain,
        ]);
        $contents = $this->responseContents($this->httpClient->request($model));
        $zones = self::normalizeRecordList($contents['Items'] ?? []);

        foreach ($zones as $zone) {
            if (($zone['Domain'] ?? null) === $domain) {
                return $this->requireInt($zone['Id'] ?? null, 'Bunny DNS zone id');
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
            'Ttl' => $this->ttl(),
        ]);

        $contents = $this->responseContents($this->httpClient->request($model));

        return $this->requireInt($contents['Id'] ?? null, 'Bunny DNS record id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getZoneRecords(): array
    {
        $contents = $this->responseContents($this->httpClient->request(new GetDnsZone($this->getZoneId())));

        return self::normalizeRecordList($contents['Records'] ?? []);
    }

    /**
     * @return array<int, array{name: string, value: string, port: int, priority: int, weight: int, ttl: int}>
     */
    private function expectedRecords(SrvRecord $srvRecord): array
    {
        $records = [[
            'name' => $this->normalizeName('_minecraft._tcp.'.$srvRecord->subdomain),
            'value' => $this->normalizeValue($this->baseTarget()),
            'port' => $srvRecord->port,
            'priority' => 0,
            'weight' => 5,
            'ttl' => $this->ttl(),
        ]];

        foreach ($this->additionalSubdomains() as $prefix) {
            $records[] = [
                'name' => $this->normalizeName('_minecraft._tcp.'.$srvRecord->subdomain.'.'.$prefix),
                'value' => $this->normalizeValue($this->additionalTarget($prefix)),
                'port' => $srvRecord->port,
                'priority' => 0,
                'weight' => 5,
                'ttl' => $this->ttl(),
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
        return $this->normalizeName(self::normalizeStringValue($record['Name'] ?? null)) === $expectedRecord['name']
            && $this->normalizeValue(self::normalizeStringValue($record['Value'] ?? null)) === $expectedRecord['value']
            && $this->intValue($record['Port'] ?? null) === $expectedRecord['port']
            && $this->intValue($record['Priority'] ?? null) === $expectedRecord['priority']
            && $this->intValue($record['Weight'] ?? null) === $expectedRecord['weight']
            && $this->intValue($record['Ttl'] ?? null) === $expectedRecord['ttl'];
    }

    private function normalizeName(string $name): string
    {
        return rtrim($name, '.');
    }

    private function normalizeValue(string $value): string
    {
        return rtrim($value, '.');
    }

    /**
     * @return array<string, mixed>
     */
    private function responseContents(mixed $response): array
    {
        if (! is_object($response) || ! method_exists($response, 'getContents')) {
            throw new RuntimeException('Bunny DNS API returned an invalid response object.');
        }

        $contents = $response->getContents();

        if (! is_array($contents)) {
            throw new RuntimeException('Bunny DNS API returned an invalid response payload.');
        }

        /** @var array<string, mixed> $contents */
        return $contents;
    }

    /**
     * @return array<int, string>
     */
    private function additionalSubdomains(): array
    {
        $subdomains = Config::array('services.dns.additional_subdomains', []);

        $normalized = [];

        foreach ($subdomains as $subdomain) {
            if (is_string($subdomain) && $subdomain !== '') {
                $normalized[] = $subdomain;
            }
        }

        return $normalized;
    }

    private function additionalTarget(string $prefix): string
    {
        return Config::string("services.dns.additional_targets.{$prefix}", $this->baseTarget());
    }

    private function baseTarget(): string
    {
        return Config::string('services.dns.base_target');
    }

    private function baseDomain(): string
    {
        return Config::string('services.dns.base_domain');
    }

    private function apiKey(): string
    {
        return Config::string('services.bunnynet.api_key');
    }

    private function ttl(): int
    {
        return Config::integer('services.dns.ttl');
    }

    private function requireInt(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new RuntimeException($label.' is missing or invalid.');
    }

    private function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }
}
