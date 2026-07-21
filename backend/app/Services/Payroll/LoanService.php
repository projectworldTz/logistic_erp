<?php

namespace App\Services\Payroll;

use App\Enums\LoanStatus;
use App\Models\EmployeeLoan;
use App\Models\UserNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoanService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function submit(EmployeeLoan $loan): EmployeeLoan
    {
        abort_if($loan->status !== LoanStatus::Draft, 409, 'Only draft loans can be submitted.');

        $loan->update(['status' => LoanStatus::PendingApproval]);

        $this->auditLogger->log(
            action: 'employee_loan.submitted',
            auditable: $loan,
            newValues: ['loan_number' => $loan->loan_number, 'principal_amount' => $loan->principal_amount],
            tenantId: $loan->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'hr.loans.approve', 'employee_loan.submitted', 'Loan awaiting approval',
            "Loan {$loan->loan_number} was submitted for approval.",
            $loan, Auth::id(),
        );

        return $loan;
    }

    /**
     * On approval, generates the fixed monthly repayment schedule and
     * marks the loan active/disbursed — this is the point of no return
     * for the installment count/amount (a real loan doesn't get its
     * terms silently changed after money moves).
     */
    public function approve(EmployeeLoan $loan): EmployeeLoan
    {
        abort_if($loan->status !== LoanStatus::PendingApproval, 409, 'Only pending loans can be approved.');

        DB::transaction(function () use ($loan) {
            $loan->update([
                'status' => LoanStatus::Active,
                'approved_by' => Auth::id(),
                'disbursed_at' => now(),
            ]);

            $totalRepayable = PayrollMath::add(
                (string) $loan->principal_amount,
                PayrollMath::percent((string) $loan->principal_amount, (string) $loan->interest_rate),
            );
            $runningTotal = '0';
            $dueDate = $loan->start_date->copy();

            for ($installment = 1; $installment <= $loan->number_of_installments; $installment++) {
                $isLast = $installment === $loan->number_of_installments;
                $amount = $isLast
                    ? PayrollMath::money(PayrollMath::sub($totalRepayable, $runningTotal))
                    : PayrollMath::money((string) $loan->installment_amount);
                $runningTotal = PayrollMath::add($runningTotal, $amount);

                $loan->schedules()->create([
                    'tenant_id' => $loan->tenant_id,
                    'installment_number' => $installment,
                    'due_date' => $dueDate->copy(),
                    'amount' => $amount,
                    'status' => 'pending',
                ]);
                $dueDate->addMonthNoOverflow();
            }
        });

        $this->auditLogger->log(
            action: 'employee_loan.approved',
            auditable: $loan,
            newValues: ['loan_number' => $loan->loan_number],
            tenantId: $loan->tenant_id,
        );

        $this->notifyCreator($loan, 'employee_loan.approved', 'Loan approved', "Loan {$loan->loan_number} was approved and disbursed.");

        return $loan;
    }

    public function reject(EmployeeLoan $loan, string $reason): EmployeeLoan
    {
        abort_if($loan->status !== LoanStatus::PendingApproval, 409, 'Only pending loans can be rejected.');

        $loan->update([
            'status' => LoanStatus::Rejected,
            'reason' => trim(($loan->reason ? $loan->reason."\n" : '')."Rejected: {$reason}"),
        ]);

        $this->auditLogger->log(
            action: 'employee_loan.rejected',
            auditable: $loan,
            newValues: ['loan_number' => $loan->loan_number, 'rejection_reason' => $reason],
            tenantId: $loan->tenant_id,
        );

        $this->notifyCreator($loan, 'employee_loan.rejected', 'Loan rejected', "Loan {$loan->loan_number} was rejected: {$reason}");

        return $loan;
    }

    private function notifyCreator(EmployeeLoan $loan, string $type, string $title, string $message): void
    {
        if (! $loan->created_by || $loan->created_by === Auth::id()) {
            return;
        }

        $now = now();

        UserNotification::query()->insert([[
            'tenant_id' => $loan->tenant_id,
            'user_id' => $loan->created_by,
            'actor_id' => Auth::id(),
            'type' => $type,
            'notifiable_type' => $loan->getMorphClass(),
            'notifiable_id' => $loan->getKey(),
            'title' => $title,
            'message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ]]);
    }
}
