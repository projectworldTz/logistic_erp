<?php

namespace App\Services\Hr;

use App\Enums\ContractStatus;
use App\Models\EmployeeContract;
use App\Models\UserNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class EmployeeContractService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function submit(EmployeeContract $contract): EmployeeContract
    {
        abort_if($contract->status !== ContractStatus::Draft, 409, 'Only draft contracts can be submitted.');

        $contract->update(['status' => ContractStatus::PendingApproval]);

        $this->auditLogger->log(
            action: 'employee_contract.submitted',
            auditable: $contract,
            newValues: ['contract_number' => $contract->contract_number],
            tenantId: $contract->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'hr.contracts.approve', 'employee_contract.submitted', 'Contract awaiting approval',
            "Contract {$contract->contract_number} was submitted for approval.",
            $contract, Auth::id(),
        );

        return $contract;
    }

    public function approve(EmployeeContract $contract): EmployeeContract
    {
        abort_if($contract->status !== ContractStatus::PendingApproval, 409, 'Only pending contracts can be approved.');

        $contract->update([
            'status' => ContractStatus::Active,
            'approved_by' => Auth::id(),
        ]);

        $this->auditLogger->log(
            action: 'employee_contract.approved',
            auditable: $contract,
            newValues: ['contract_number' => $contract->contract_number],
            tenantId: $contract->tenant_id,
        );

        $this->notifyCreator($contract, 'employee_contract.approved', 'Contract approved', "Contract {$contract->contract_number} was approved.");

        return $contract;
    }

    public function reject(EmployeeContract $contract, string $reason): EmployeeContract
    {
        abort_if($contract->status !== ContractStatus::PendingApproval, 409, 'Only pending contracts can be rejected.');

        $contract->update([
            'status' => ContractStatus::Draft,
            'notes' => trim(($contract->notes ? $contract->notes."\n" : '')."Rejected: {$reason}"),
        ]);

        $this->auditLogger->log(
            action: 'employee_contract.rejected',
            auditable: $contract,
            newValues: ['contract_number' => $contract->contract_number, 'rejection_reason' => $reason],
            tenantId: $contract->tenant_id,
        );

        $this->notifyCreator($contract, 'employee_contract.rejected', 'Contract rejected', "Contract {$contract->contract_number} was rejected: {$reason}");

        return $contract;
    }

    private function notifyCreator(EmployeeContract $contract, string $type, string $title, string $message): void
    {
        if (! $contract->created_by || $contract->created_by === Auth::id()) {
            return;
        }

        $now = now();

        UserNotification::query()->insert([[
            'tenant_id' => $contract->tenant_id,
            'user_id' => $contract->created_by,
            'actor_id' => Auth::id(),
            'type' => $type,
            'notifiable_type' => $contract->getMorphClass(),
            'notifiable_id' => $contract->getKey(),
            'title' => $title,
            'message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ]]);
    }
}
