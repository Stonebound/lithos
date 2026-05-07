<?php

declare(strict_types=1);

namespace App\Concerns;

trait NormalizesRecordLists
{
    /**
     * @return list<array<string, mixed>>
     */
    protected static function normalizeRecordList(mixed $records): array
    {
        if (! is_array($records)) {
            return [];
        }

        $normalized = [];

        foreach ($records as $record) {
            if (is_array($record)) {
                /** @var array<string, mixed> $record */
                $normalized[] = $record;
            }
        }

        return $normalized;
    }
}
