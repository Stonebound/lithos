<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\WhitelistUser;
use App\Services\AuditService;

class WhitelistUserObserver
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function created(WhitelistUser $user): void
    {
        $this->auditService->logCreate($user);
    }

    public function updated(WhitelistUser $user): void
    {
        $this->auditService->logUpdate($user);
    }

    public function deleted(WhitelistUser $user): void
    {
        $this->auditService->logDelete($user);
    }
}
