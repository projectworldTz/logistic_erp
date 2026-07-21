<?php

namespace App\Observers;

use App\Models\EmployeeLoan;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class EmployeeLoanObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(EmployeeLoan $loan): void
    {
        $loan->loan_number = 'LOAN-'.now()->format('Y').'-'.str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT);
        $loan->saveQuietly();

        $this->auditLogger->log(
            action: 'employee_loan.created',
            auditable: $loan,
            newValues: $loan->only(['employee_id', 'loan_number', 'principal_amount', 'number_of_installments']),
            tenantId: $loan->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'hr.loans.manage', 'employee_loan.created', 'New employee loan',
            "Loan {$loan->loan_number} was created and needs review.",
            $loan, Auth::id(),
        );
    }

    public function updated(EmployeeLoan $loan): void
    {
        $this->auditLogger->log(
            action: 'employee_loan.updated',
            auditable: $loan,
            oldValues: $loan->getOriginal(),
            newValues: $loan->getChanges(),
            tenantId: $loan->tenant_id,
        );
    }

    public function deleted(EmployeeLoan $loan): void
    {
        $this->auditLogger->log(
            action: 'employee_loan.deleted',
            auditable: $loan,
            oldValues: $loan->only(['employee_id', 'loan_number']),
            tenantId: $loan->tenant_id,
        );
    }
}
