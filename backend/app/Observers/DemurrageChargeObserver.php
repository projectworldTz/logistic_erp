<?php

namespace App\Observers;

use App\Models\DemurrageCharge;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class DemurrageChargeObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(DemurrageCharge $charge): void
    {
        $this->auditLogger->log(
            action: 'demurrage_charge.created',
            auditable: $charge,
            newValues: $charge->only(['container_id', 'customer_id', 'amount', 'chargeable_days', 'status']),
            tenantId: $charge->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'demurrage.charges.view', 'demurrage_charge.created', 'Demurrage charge calculated',
            "A demurrage charge of {$charge->currency} {$charge->amount} was calculated for container {$charge->container->container_number}.",
            $charge, Auth::id(),
        );
    }

    public function updated(DemurrageCharge $charge): void
    {
        $this->auditLogger->log(
            action: 'demurrage_charge.updated',
            auditable: $charge,
            oldValues: $charge->getOriginal(),
            newValues: $charge->getChanges(),
            tenantId: $charge->tenant_id,
        );
    }
}
