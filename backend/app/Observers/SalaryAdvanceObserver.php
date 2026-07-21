<?php

namespace App\Observers;

use App\Models\SalaryAdvance;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class SalaryAdvanceObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(SalaryAdvance $advance): void
    {
        $advance->advance_number = 'ADV-'.now()->format('Y').'-'.str_pad((string) $advance->id, 5, '0', STR_PAD_LEFT);
        $advance->saveQuietly();

        $this->auditLogger->log(
            action: 'salary_advance.created',
            auditable: $advance,
            newValues: $advance->only(['employee_id', 'advance_number', 'amount', 'number_of_installments']),
            tenantId: $advance->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'hr.advances.manage', 'salary_advance.created', 'New salary advance request',
            "Salary advance {$advance->advance_number} was created and needs review.",
            $advance, Auth::id(),
        );
    }

    public function updated(SalaryAdvance $advance): void
    {
        $this->auditLogger->log(
            action: 'salary_advance.updated',
            auditable: $advance,
            oldValues: $advance->getOriginal(),
            newValues: $advance->getChanges(),
            tenantId: $advance->tenant_id,
        );
    }

    public function deleted(SalaryAdvance $advance): void
    {
        $this->auditLogger->log(
            action: 'salary_advance.deleted',
            auditable: $advance,
            oldValues: $advance->only(['employee_id', 'advance_number']),
            tenantId: $advance->tenant_id,
        );
    }
}
