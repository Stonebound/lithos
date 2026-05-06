<?php

declare(strict_types=1);

namespace App\Services\Dns;

use App\Models\SrvRecord;

interface SrvDnsProvider
{
    public function name(): string;

    /**
     * @return array<int, int|string>
     */
    public function createRecords(SrvRecord $srvRecord): array;

    /**
     * @return array<int, int|string>
     */
    public function findMatchingRecordIds(SrvRecord $srvRecord): array;

    public function updateRecords(SrvRecord $srvRecord): void;

    public function deleteRecords(SrvRecord $srvRecord): void;
}
