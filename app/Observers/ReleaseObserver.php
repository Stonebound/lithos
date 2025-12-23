<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Release;
use App\Services\AuditService;

class ReleaseObserver
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Handle the Release "created" event.
     */
    public function created(Release $release): void
    {
        $this->auditService->logCreate($release);
    }

    /**
     * Handle the Release "updated" event.
     */
    public function updated(Release $release): void
    {
        $this->auditService->logUpdate($release);
    }

    /**
     * Handle the Release "deleted" event.
     */
    public function deleted(Release $release): void
    {
        $this->auditService->logDelete($release);
    }
}
