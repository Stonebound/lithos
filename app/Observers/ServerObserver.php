<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Server;
use App\Services\AuditService;

class ServerObserver
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Handle the Server "created" event.
     */
    public function created(Server $server): void
    {
        $this->auditService->logCreate($server);
    }

    /**
     * Handle the Server "updated" event.
     */
    public function updated(Server $server): void
    {
        $this->auditService->logUpdate($server);
    }

    /**
     * Handle the Server "deleted" event.
     */
    public function deleted(Server $server): void
    {
        $this->auditService->logDelete($server);
    }
}
