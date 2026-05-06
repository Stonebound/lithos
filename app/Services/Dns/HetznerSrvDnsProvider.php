<?php

declare(strict_types=1);

namespace App\Services\Dns;

use App\Models\SrvRecord;
use LKDev\HetznerCloud\HetznerAPIClient;
use LKDev\HetznerCloud\Models\Zones\Record;
use LKDev\HetznerCloud\Models\Zones\RRSet;
use LKDev\HetznerCloud\Models\Zones\Zone;
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
        $recordIds = [];

        $recordIds[] = $this->createRrset($zone, $srvRecord, $this->buildRrsetName($srvRecord->subdomain), (string) config('services.dns.base_target'));

        foreach (config('services.dns.additional_subdomains', []) as $prefix) {
            $recordIds[] = $this->createRrset(
                $zone,
                $srvRecord,
                $this->buildRrsetName($srvRecord->subdomain, (string) $prefix),
                (string) (config("services.dns.additional_targets.{$prefix}") ?? config('services.dns.base_target')),
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
            $rrset = $zone->getRRSetById((string) $recordId);

            if ($rrset === null) {
                continue;
            }

            $rrset->update([
                'name' => $rrset->name,
                'type' => $rrset->type,
                'ttl' => (int) config('services.dns.ttl'),
                'records' => [new Record($this->buildSrvValue($srvRecord->port, $targets[$index] ?? (string) config('services.dns.base_target')), self::COMMENT)],
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
            (int) config('services.dns.ttl'),
        );

        $rrset = $response?->rrset;

        if (! $rrset instanceof RRSet) {
            throw new RuntimeException('Hetzner DNS API did not return an rrset identifier.');
        }

        return $rrset->id;
    }

    private function getZone(): Zone
    {
        $zone = $this->client()->zones()->getByName((string) config('services.dns.base_domain'));

        if ($zone === null) {
            throw new RuntimeException('DNS zone not found for domain: '.config('services.dns.base_domain'));
        }

        return $zone;
    }

    private function client(): HetznerAPIClient
    {
        return $this->api ?? new HetznerAPIClient(
            (string) config('services.hetzner.api_token'),
            (string) config('services.hetzner.base_url', 'https://api.hetzner.cloud/v1/'),
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
            'ttl' => (int) config('services.dns.ttl'),
            'value' => $this->buildSrvValue($srvRecord->port, (string) config('services.dns.base_target')),
        ]];

        foreach (config('services.dns.additional_subdomains', []) as $prefix) {
            $rrsets[] = [
                'name' => $this->buildRrsetName($srvRecord->subdomain, (string) $prefix),
                'ttl' => (int) config('services.dns.ttl'),
                'value' => $this->buildSrvValue($srvRecord->port, (string) (config("services.dns.additional_targets.{$prefix}") ?? config('services.dns.base_target'))),
            ];
        }

        return $rrsets;
    }

    /**
     * @param  array{name: string, ttl: int, value: string}  $expectedRrset
     */
    private function rrsetMatches(RRSet $rrset, array $expectedRrset): bool
    {
        return $rrset->type === 'SRV'
            && $rrset->name === $expectedRrset['name']
            && (int) $rrset->ttl === $expectedRrset['ttl']
            && count($rrset->records) === 1
            && $this->normalizeSrvValue($rrset->records[0]->value) === $this->normalizeSrvValue($expectedRrset['value']);
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
        $targets = [(string) config('services.dns.base_target')];

        foreach (config('services.dns.additional_subdomains', []) as $prefix) {
            $targets[] = (string) (config("services.dns.additional_targets.{$prefix}") ?? config('services.dns.base_target'));
        }

        return $targets;
    }
}
