<?php

declare(strict_types=1);

namespace App\Services\Dns;

use App\Models\SrvRecord;
use Illuminate\Support\Facades\Config;
use LKDev\HetznerCloud\HetznerAPIClient;
use LKDev\HetznerCloud\Models\Zones\Record;
use LKDev\HetznerCloud\Models\Zones\RRSet;
use LKDev\HetznerCloud\Models\Zones\Zone;
use LKDev\HetznerCloud\Models\Zones\Zones;
use RuntimeException;

class HetznerSrvDnsProvider implements SrvDnsProvider
{
    private const COMMENT = 'Managed by Lithos';

    public function __construct(
        private readonly ?HetznerAPIClient $api = null,
    ) {}

    public function name(): string
    {
        return 'hetzner';
    }

    public function createRecords(SrvRecord $srvRecord): array
    {
        $zone = $this->getZone();
        /** @var array<int, string> $recordIds */
        $recordIds = [];

        $recordIds[] = $this->createRrset($zone, $srvRecord, $this->buildRrsetName($srvRecord->subdomain), $this->baseTarget());

        foreach ($this->additionalSubdomains() as $prefix) {
            $recordIds[] = $this->createRrset(
                $zone,
                $srvRecord,
                $this->buildRrsetName($srvRecord->subdomain, $prefix),
                $this->additionalTarget($prefix),
            );
        }

        return $recordIds;
    }

    public function findMatchingRecordIds(SrvRecord $srvRecord): array
    {
        $rrsets = $this->getZone()->allRRSets();
        $matches = [];
        $usedIndexes = [];

        foreach ($this->expectedRrsets($srvRecord) as $expectedRrset) {
            $matchedIndex = null;

            foreach ($rrsets as $index => $rrset) {
                if (in_array($index, $usedIndexes, true)) {
                    continue;
                }

                if (! $this->rrsetMatches($rrset, $expectedRrset)) {
                    continue;
                }

                $matchedIndex = $index;
                $usedIndexes[] = $index;
                $matches[] = $rrset->id;

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

        $zone = $this->getZone();
        $targets = $this->targets();

        foreach ($srvRecord->record_ids as $index => $recordId) {
            if (! is_string($recordId) && ! is_int($recordId)) {
                continue;
            }

            $rrset = $zone->getRRSetById((string) $recordId);

            if ($rrset === null) {
                continue;
            }

            $rrset->update([
                'name' => $rrset->name,
                'type' => $rrset->type,
                'ttl' => $this->ttl(),
                'records' => [new Record($this->buildSrvValue($srvRecord->port, $targets[$index] ?? $this->baseTarget()), self::COMMENT)],
            ]);
        }
    }

    public function deleteRecords(SrvRecord $srvRecord): void
    {
        if (empty($srvRecord->record_ids)) {
            return;
        }

        $zone = $this->getZone();

        foreach ($srvRecord->record_ids as $recordId) {
            if (! is_string($recordId) && ! is_int($recordId)) {
                continue;
            }

            $rrset = $zone->getRRSetById((string) $recordId);

            if ($rrset === null) {
                continue;
            }

            $rrset->delete();
        }
    }

    private function createRrset(Zone $zone, SrvRecord $srvRecord, string $name, string $target): string
    {
        $response = $zone->createRRSet(
            $name,
            'SRV',
            [new Record($this->buildSrvValue($srvRecord->port, $target), self::COMMENT)],
            $this->ttl(),
        );

        $rrset = $response?->getResponsePart('rrset');

        if (! $rrset instanceof RRSet) {
            throw new RuntimeException('Hetzner DNS API did not return an rrset identifier.');
        }

        return $rrset->id;
    }

    private function getZone(): Zone
    {
        $zones = $this->client()->zones();

        if (! $zones instanceof Zones) {
            throw new RuntimeException('Hetzner DNS zones client is unavailable.');
        }

        $baseDomain = $this->baseDomain();
        $zone = $zones->getByName($baseDomain);

        if ($zone === null) {
            throw new RuntimeException('DNS zone not found for domain: '.$baseDomain);
        }

        return $zone;
    }

    private function client(): HetznerAPIClient
    {
        return $this->api ?? new HetznerAPIClient(
            $this->apiToken(),
            $this->apiBaseUrl(),
        );
    }

    private function buildRrsetName(string $subdomain, string $prefix = ''): string
    {
        return trim('_minecraft._tcp.'.$subdomain.'.'.$prefix, '.');
    }

    private function buildSrvValue(int $port, string $target): string
    {
        return sprintf('0 5 %d %s', $port, $this->normalizeTarget($target));
    }

    private function normalizeTarget(string $target): string
    {
        return rtrim($target, '.').'.';
    }

    /**
     * @return array<int, array{name: string, ttl: int, value: string}>
     */
    private function expectedRrsets(SrvRecord $srvRecord): array
    {
        $rrsets = [[
            'name' => $this->buildRrsetName($srvRecord->subdomain),
            'ttl' => $this->ttl(),
            'value' => $this->buildSrvValue($srvRecord->port, $this->baseTarget()),
        ]];

        foreach ($this->additionalSubdomains() as $prefix) {
            $rrsets[] = [
                'name' => $this->buildRrsetName($srvRecord->subdomain, $prefix),
                'ttl' => $this->ttl(),
                'value' => $this->buildSrvValue($srvRecord->port, $this->additionalTarget($prefix)),
            ];
        }

        return $rrsets;
    }

    /**
     * @param  array{name: string, ttl: int, value: string}  $expectedRrset
     */
    private function rrsetMatches(RRSet $rrset, array $expectedRrset): bool
    {
        $record = $rrset->records[0] ?? null;

        return $rrset->type === 'SRV'
            && $rrset->name === $expectedRrset['name']
            && $rrset->ttl === $expectedRrset['ttl']
            && count($rrset->records) === 1
            && $record instanceof Record
            && $this->normalizeSrvValue($record->value) === $this->normalizeSrvValue($expectedRrset['value']);
    }

    private function normalizeSrvValue(string $value): string
    {
        $parts = preg_split('/\s+/', trim($value));

        if (! is_array($parts) || count($parts) !== 4) {
            return trim($value);
        }

        [$priority, $weight, $port, $target] = $parts;

        return sprintf('%s %s %s %s', $priority, $weight, $port, rtrim($target, '.'));
    }

    /**
     * @return array<int, string>
     */
    private function targets(): array
    {
        $targets = [$this->baseTarget()];

        foreach ($this->additionalSubdomains() as $prefix) {
            $targets[] = $this->additionalTarget($prefix);
        }

        return $targets;
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

    private function apiToken(): string
    {
        return Config::string('services.hetzner.api_token');
    }

    private function apiBaseUrl(): string
    {
        return Config::string('services.hetzner.base_url', 'https://api.hetzner.cloud/v1/');
    }

    private function ttl(): int
    {
        return Config::integer('services.dns.ttl');
    }
}
