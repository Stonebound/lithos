<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\OverrideRule;
use App\Services\AuditService;

class OverrideRuleObserver
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Handle the OverrideRule "created" event.
     */
    public function created(OverrideRule $overrideRule): void
    {
        $this->auditService->logCreate($overrideRule);
    }

    /**
     * Handle the OverrideRule "updated" event.
     */
    public function updated(OverrideRule $overrideRule): void
    {
        $this->auditService->logUpdate($overrideRule);
    }

    /**
     * Handle the OverrideRule "deleted" event.
     */
    public function deleted(OverrideRule $overrideRule): void
    {
        $this->auditService->logDelete($overrideRule);
    }
}
