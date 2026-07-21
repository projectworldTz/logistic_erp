<?php

namespace App\Services\Payroll;

use App\Enums\SalaryAdvanceStatus;
use App\Models\SalaryAdvance;
use App\Models\UserNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalaryAdvanceService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function submit(SalaryAdvance $advance): SalaryAdvance
    {
        abort_if($advance->status !== SalaryAdvanceStatus::Draft, 409, 'Only draft advances can be submitted.');

        $advance->update(['status' => SalaryAdvanceStatus::PendingApproval]);

        $this->auditLogger->log(
            action: 'salary_advance.submitted',
            auditable: $advance,
            newValues: ['advance_number' => $advance->advance_number, 'amount' => $advance->amount],
            tenantId: $advance->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'hr.advances.approve', 'salary_advance.submitted', 'Salary advance awaiting approval',
            "Salary advance {$advance->advance_number} was submitted for approval.",
            $advance, Auth::id(),
        );

        return $advance;
    }

    public function approve(SalaryAdvance $advance): SalaryAdvance
    {
        abort_if($advance->status !== SalaryAdvanceStatus::PendingApproval, 409, 'Only pending advances can be approved.');

        DB::transaction(function () use ($advance) {
            $advance->update([
                'status' => SalaryAdvanceStatus::Active,
                'approved_by' => Auth::id(),
                'disbursed_at' => now(),
            ]);

            $runningTotal = '0';
            $dueDate = $advance->request_date->copy()->addMonthNoOverflow();

            for ($installment = 1; $installment <= $advance->number_of_installments; $installment++) {
                $isLast = $installment === $advance->number_of_installments;
                $amount = $isLast
                    ? PayrollMath::money(PayrollMath::sub((string) $advance->amount, $runningTotal))
                    : PayrollMath::money((string) $advance->installment_amount);
                $runningTotal = PayrollMath::add($runningTotal, $amount);

                $advance->schedules()->create([
                    'tenant_id' => $advance->tenant_id,
                    'installment_number' => $installment,
                    'due_date' => $dueDate->copy(),
                    'amount' => $amount,
                    'status' => 'pending',
                ]);
                $dueDate->addMonthNoOverflow();
            }
        });

        $this->auditLogger->log(
            action: 'salary_advance.approved',
            auditable: $advance,
            newValues: ['advance_number' => $advance->advance_number],
            tenantId: $advance->tenant_id,
        );

        $this->notifyCreator($advance, 'salary_advance.approved', 'Salary advance approved', "Salary advance {$advance->advance_number} was approved and disbursed.");

        return $advance;
    }

    public function reject(SalaryAdvance $advance, string $reason): SalaryAdvance
    {
        abort_if($advance->status !== SalaryAdvanceStatus::PendingApproval, 409, 'Only pending advances can be rejected.');

        $advance->update([
            'status' => SalaryAdvanceStatus::Rejected,
            'reason' => trim(($advance->reason ? $advance->reason."\n" : '')."Rejected: {$reason}"),
        ]);

        $this->auditLogger->log(
            action: 'salary_advance.rejected',
            auditable: $advance,
            newValues: ['advance_number' => $advance->advance_number, 'rejection_reason' => $reason],
            tenantId: $advance->tenant_id,
        );

        $this->notifyCreator($advance, 'salary_advance.rejected', 'Salary advance rejected', "Salary advance {$advance->advance_number} was rejected: {$reason}");

        return $advance;
    }

    private function notifyCreator(SalaryAdvance $advance, string $type, string $title, string $message): void
    {
        if (! $advance->created_by || $advance->created_by === Auth::id()) {
            return;
        }

        $now = now();

        UserNotification::query()->insert([[
            'tenant_id' => $advance->tenant_id,
            'user_id' => $advance->created_by,
            'actor_id' => Auth::id(),
            'type' => $type,
            'notifiable_type' => $advance->getMorphClass(),
            'notifiable_id' => $advance->getKey(),
            'title' => $title,
            'message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ]]);
    }
}
