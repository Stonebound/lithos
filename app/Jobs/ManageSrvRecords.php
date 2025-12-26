<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SrvRecord;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use ToshY\BunnyNet\BunnyHttpClient;
use ToshY\BunnyNet\Enum\Endpoint;
use ToshY\BunnyNet\Model\Api\Base\DnsZone\AddDnsRecord;
use ToshY\BunnyNet\Model\Api\Base\DnsZone\DeleteDnsRecord;
use ToshY\BunnyNet\Model\Api\Base\DnsZone\ListDnsZones;
use ToshY\BunnyNet\Model\Api\Base\DnsZone\UpdateDnsRecord;

class ManageSrvRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected BunnyHttpClient $httpClient;

    public function __construct(
        public SrvRecord $srvRecord,
        public string $action,
        public array $changes = [],
        protected ?Client $client = null,
    ) {}

    public function handle(): void
    {
        $this->client = $this->client ?? app(Client::class);

        $this->httpClient = new BunnyHttpClient(
            client: $this->client,
            apiKey: config('services.bunnynet.api_key'),
            baseUrl: Endpoint::BASE,
        );

        $zoneId = $this->getZoneId();

        if ($this->action === 'delete') {
            $this->deleteRecords($zoneId);
        } elseif ($this->action === 'create') {
            $this->createRecords($zoneId);
        } elseif ($this->action === 'update') {
            if (isset($this->changes['subdomain'])) {
                // Subdomain changed, recreate records
                $this->deleteRecords($zoneId);
                $this->createRecords($zoneId);
            } elseif (isset($this->changes['port'])) {
                // Only port changed, update existing records
                $this->updateRecords($zoneId);
            }
        }
    }

    private function getZoneId(): int
    {
        $model = new ListDnsZones([
            'page' => 1,
            'perPage' => 10,
            'search' => config('services.bunnynet.base_domain'),
        ]);
        $response = $this->httpClient->request($model);
        $zones = $response->getContents()['Items'] ?? [];

        foreach ($zones as $zone) {
            if ($zone['Domain'] === config('services.bunnynet.base_domain')) {
                return $zone['Id'];
            }
        }

        throw new \Exception('DNS Zone not found for domain: '.config('services.bunnynet.base_domain'));
    }

    private function updateRecords(int $zoneId): void
    {
        if (! $this->srvRecord->record_ids) {
            return;
        }

        foreach ($this->srvRecord->record_ids as $recordId) {
            $model = new UpdateDnsRecord($zoneId, $recordId, [
                'Port' => $this->srvRecord->port,
            ]);
            $this->httpClient->request($model);
        }
    }

    private function deleteRecords(int $zoneId): void
    {
        if (! $this->srvRecord->record_ids) {
            return;
        }

        foreach ($this->srvRecord->record_ids as $recordId) {
            $model = new DeleteDnsRecord($zoneId, $recordId);
            $this->httpClient->request($model);
        }
    }

    private function createRecords(int $zoneId): void
    {
        $recordIds = [];

        // Base record
        $recordIds[] = $this->addRecord($zoneId, $this->srvRecord->subdomain, config('services.bunnynet.base_target'));

        // Additional records
        foreach (config('services.bunnynet.additional_subdomains') as $prefix) {
            $recordIds[] = $this->addRecord($zoneId, $this->srvRecord->subdomain, config('services.bunnynet.additional_targets')[$prefix] ?? config('services.bunnynet.base_target'), $prefix);
        }

        $this->srvRecord->updateQuietly(['record_ids' => $recordIds]);
    }

    private function addRecord(int $zoneId, string $subdomain, string $target, string $prefix = ''): int
    {
        $name = '_minecraft._tcp.'.$subdomain.'.'.($prefix ? $prefix.'.' : '');

        $model = new AddDnsRecord($zoneId, [
            'Type' => 'SRV',
            'Name' => $name,
            'Value' => $target,
            'Port' => $this->srvRecord->port,
            'Priority' => 0,
            'Weight' => 5,
            'Ttl' => config('services.bunnynet.ttl'),
        ]);

        $response = $this->httpClient->request($model);

        return $response->getContents()['Id'];
    }
}
