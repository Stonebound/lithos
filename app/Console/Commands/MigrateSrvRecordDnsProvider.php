<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SrvRecord;
use App\Services\Dns\SrvDnsProvider;
use App\Services\Dns\SrvDnsProviderResolver;
use Illuminate\Console\Command;
use Throwable;

class MigrateSrvRecordDnsProvider extends Command
{
    protected $signature = 'srv-records:migrate-provider {--dry-run : Show what would be migrated without persisting changes}';

    protected $description = 'Adopt or create SRV records on the currently configured DNS provider';

    /**
     * Execute the console command.
     */
    public function handle(SrvDnsProviderResolver $resolver): int
    {
        if (! $resolver->isConfigured()) {
            $this->error('The configured DNS provider is not fully configured.');

            return Command::FAILURE;
        }

        $providerName = $resolver->providerName();
        $provider = $resolver->resolve();
        $srvRecords = SrvRecord::query()->orderBy('id')->get();

        if ($srvRecords->isEmpty()) {
            $this->info('No SRV records found.');

            return Command::SUCCESS;
        }

        $this->info(sprintf('Migrating %d SRV record(s) to [%s].', $srvRecords->count(), $providerName));

        $summary = [
            'adopted' => 0,
            'created' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($srvRecords as $srvRecord) {
            try {
                $result = $this->migrateSrvRecord($srvRecord, $provider, $providerName, (bool) $this->option('dry-run'));
                $summary[$result]++;
            } catch (Throwable $throwable) {
                $summary['failed']++;
                report($throwable);

                $this->error(sprintf(
                    'Failed to migrate [%s]: %s',
                    $srvRecord->subdomain,
                    $throwable->getMessage(),
                ));
            }
        }

        $this->table(
            ['adopted', 'created', 'skipped', 'failed'],
            [[
                $summary['adopted'],
                $summary['created'],
                $summary['skipped'],
                $summary['failed'],
            ]],
        );

        return $summary['failed'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function migrateSrvRecord(SrvRecord $srvRecord, SrvDnsProvider $provider, string $providerName, bool $dryRun): string
    {
        $previousProvider = $srvRecord->dns_provider;

        if ($srvRecord->dns_provider === $providerName && ! empty($srvRecord->record_ids)) {
            $this->line(sprintf('Skipping [%s]: already managed by [%s].', $srvRecord->subdomain, $providerName));

            return 'skipped';
        }

        $matchingRecordIds = $provider->findMatchingRecordIds($srvRecord);

        if ($matchingRecordIds !== []) {
            if ($dryRun) {
                $this->line(sprintf('Would adopt [%s] on [%s].', $srvRecord->subdomain, $providerName));

                return 'adopted';
            }

            $srvRecord->updateQuietly([
                'record_ids' => $matchingRecordIds,
                'dns_provider' => $providerName,
            ]);

            $this->info(sprintf('Adopted [%s] on [%s].', $srvRecord->subdomain, $providerName));

            return 'adopted';
        }

        if ($dryRun) {
            $this->line(sprintf('Would create [%s] on [%s].', $srvRecord->subdomain, $providerName));

            return 'created';
        }

        $srvRecord->updateQuietly([
            'record_ids' => $provider->createRecords($srvRecord),
            'dns_provider' => $providerName,
        ]);

        $this->info(sprintf('Created [%s] on [%s].', $srvRecord->subdomain, $providerName));

        if (filled($previousProvider) && $previousProvider !== $providerName) {
            $this->warn(sprintf(
                'The previous provider [%s] may still contain stale SRV records for [%s].',
                $previousProvider,
                $srvRecord->subdomain,
            ));
        }

        return 'created';
    }
}
