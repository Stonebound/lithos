<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\AuditService;

class UserObserver
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->auditService->logCreate($user);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $this->auditService->logUpdate($user);
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $this->auditService->logDelete($user);
    }
}
