<?php

namespace App\Observers;

use App\Models\EmployeeContract;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class EmployeeContractObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(EmployeeContract $contract): void
    {
        $contract->contract_number = 'CON-'.now()->format('Y').'-'.str_pad((string) $contract->id, 5, '0', STR_PAD_LEFT);
        $contract->saveQuietly();

        $this->auditLogger->log(
            action: 'employee_contract.created',
            auditable: $contract,
            newValues: $contract->only(['employee_id', 'contract_number', 'employment_type', 'basic_salary', 'status']),
            tenantId: $contract->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'hr.contracts.manage', 'employee_contract.created', 'New employee contract',
            "Contract {$contract->contract_number} was created and needs review.",
            $contract, Auth::id(),
        );
    }

    public function updated(EmployeeContract $contract): void
    {
        $this->auditLogger->log(
            action: 'employee_contract.updated',
            auditable: $contract,
            oldValues: $contract->getOriginal(),
            newValues: $contract->getChanges(),
            tenantId: $contract->tenant_id,
        );
    }

    public function deleted(EmployeeContract $contract): void
    {
        $this->auditLogger->log(
            action: 'employee_contract.deleted',
            auditable: $contract,
            oldValues: $contract->only(['employee_id', 'contract_number']),
            tenantId: $contract->tenant_id,
        );
    }
}
