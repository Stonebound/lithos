<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\ManageSrvRecords;
use App\Models\SrvRecord;

class SrvRecordObserver
{
    public function created(SrvRecord $srvRecord): void
    {
        ManageSrvRecords::dispatch($srvRecord, 'create');
    }

    public function updated(SrvRecord $srvRecord): void
    {
        $changes = $srvRecord->getChanges();
        if (isset($changes['subdomain']) || isset($changes['port'])) {
            ManageSrvRecords::dispatch($srvRecord, 'update', $changes);
        }
    }

    public function deleted(SrvRecord $srvRecord): void
    {
        ManageSrvRecords::dispatch($srvRecord, 'delete');
    }
}
