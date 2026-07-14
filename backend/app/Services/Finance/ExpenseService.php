<?php

namespace App\Services\Finance;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\UserNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class ExpenseService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function submit(Expense $expense): Expense
    {
        abort_if($expense->status !== ExpenseStatus::Draft, 409, 'Only draft expenses can be submitted.');

        $expense->update(['status' => ExpenseStatus::Submitted]);

        $this->auditLogger->log(
            action: 'expense.submitted',
            auditable: $expense,
            newValues: ['expense_number' => $expense->expense_number],
            tenantId: $expense->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'expenses.items.approve', 'expense.submitted', 'Expense awaiting approval',
            "Expense {$expense->expense_number} ({$expense->currency} {$expense->amount}) was submitted for approval.",
            $expense, Auth::id(),
        );

        return $expense;
    }

    public function approve(Expense $expense): Expense
    {
        abort_if($expense->status !== ExpenseStatus::Submitted, 409, 'Only submitted expenses can be approved.');

        $expense->update([
            'status' => ExpenseStatus::Approved,
            'approved_by' => Auth::id(),
        ]);

        $this->auditLogger->log(
            action: 'expense.approved',
            auditable: $expense,
            newValues: ['expense_number' => $expense->expense_number],
            tenantId: $expense->tenant_id,
        );

        $this->notifyCreator($expense, 'expense.approved', 'Expense approved', "Expense {$expense->expense_number} was approved.");

        return $expense;
    }

    public function reject(Expense $expense, string $reason): Expense
    {
        abort_if($expense->status !== ExpenseStatus::Submitted, 409, 'Only submitted expenses can be rejected.');

        $expense->update([
            'status' => ExpenseStatus::Rejected,
            'approved_by' => Auth::id(),
            'rejection_reason' => $reason,
        ]);

        $this->auditLogger->log(
            action: 'expense.rejected',
            auditable: $expense,
            newValues: ['expense_number' => $expense->expense_number, 'rejection_reason' => $reason],
            tenantId: $expense->tenant_id,
        );

        $this->notifyCreator($expense, 'expense.rejected', 'Expense rejected', "Expense {$expense->expense_number} was rejected: {$reason}");

        return $expense;
    }

    public function markPaid(Expense $expense): Expense
    {
        abort_if($expense->status !== ExpenseStatus::Approved, 409, 'Only approved expenses can be marked paid.');

        $expense->update([
            'status' => ExpenseStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->auditLogger->log(
            action: 'expense.paid',
            auditable: $expense,
            newValues: ['expense_number' => $expense->expense_number],
            tenantId: $expense->tenant_id,
        );

        return $expense;
    }

    private function notifyCreator(Expense $expense, string $type, string $title, string $message): void
    {
        if (! $expense->created_by || $expense->created_by === Auth::id()) {
            return;
        }

        $now = now();

        UserNotification::query()->insert([[
            'tenant_id' => $expense->tenant_id,
            'user_id' => $expense->created_by,
            'actor_id' => Auth::id(),
            'type' => $type,
            'notifiable_type' => $expense->getMorphClass(),
            'notifiable_id' => $expense->getKey(),
            'title' => $title,
            'message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ]]);
    }
}
