<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SrvRecord;
use App\Services\Dns\SrvDnsProviderResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;

class ManageSrvRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public SrvRecord $srvRecord,
        public string $action,
        public array $changes = [],
    ) {}

    public function handle(): void
    {
        /** @var SrvDnsProviderResolver $resolver */
        $resolver = app(SrvDnsProviderResolver::class);
        $providerName = $resolver->providerName();

        if ($this->action === 'delete') {
            $this->ensureRecordProviderMatches($providerName);
            $resolver->resolve()->deleteRecords($this->srvRecord);
        } elseif ($this->action === 'create') {
            $this->srvRecord->updateQuietly([
                'record_ids' => $resolver->resolve()->createRecords($this->srvRecord),
                'dns_provider' => $providerName,
            ]);
        } elseif ($this->action === 'update') {
            if (isset($this->changes['subdomain'])) {
                $this->ensureRecordProviderMatches($providerName);
                $provider = $resolver->resolve();
                $provider->deleteRecords($this->srvRecord);
                $this->srvRecord->updateQuietly([
                    'record_ids' => $provider->createRecords($this->srvRecord),
                    'dns_provider' => $providerName,
                ]);
            } elseif (isset($this->changes['port'])) {
                $this->ensureRecordProviderMatches($providerName);
                $resolver->resolve()->updateRecords($this->srvRecord);
            }
        }
    }

    private function ensureRecordProviderMatches(string $providerName): void
    {
        if (empty($this->srvRecord->record_ids)) {
            return;
        }

        if (! filled($this->srvRecord->dns_provider)) {
            throw new RuntimeException('SRV record has stored DNS record IDs but no provider. Run srv-records:migrate-provider first.');
        }

        if ($this->srvRecord->dns_provider !== $providerName) {
            throw new RuntimeException(sprintf(
                'SRV record is managed by [%s], but the active DNS provider is [%s]. Run srv-records:migrate-provider first.',
                $this->srvRecord->dns_provider,
                $providerName,
            ));
        }
    }
}
