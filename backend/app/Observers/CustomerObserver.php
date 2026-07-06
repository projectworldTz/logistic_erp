<?php

namespace App\Observers;

use App\Models\Customer;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class CustomerObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(Customer $customer): void
    {
        $this->auditLogger->log(
            action: 'customer.created',
            auditable: $customer,
            newValues: $customer->only(['company_name', 'status']),
            tenantId: $customer->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'crm.customers.view', 'customer.created', 'New customer',
            "New customer: {$customer->company_name}.",
            $customer, Auth::id(),
        );
    }

    public function updated(Customer $customer): void
    {
        $this->auditLogger->log(
            action: 'customer.updated',
            auditable: $customer,
            oldValues: $customer->getOriginal(),
            newValues: $customer->getChanges(),
            tenantId: $customer->tenant_id,
        );
    }

    public function deleted(Customer $customer): void
    {
        $this->auditLogger->log(
            action: 'customer.deleted',
            auditable: $customer,
            oldValues: $customer->only(['company_name']),
            tenantId: $customer->tenant_id,
        );
    }
}
