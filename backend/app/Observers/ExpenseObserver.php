<?php

namespace App\Observers;

use App\Models\Expense;
use App\Services\Audit\AuditLogger;

class ExpenseObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(Expense $expense): void
    {
        $expense->expense_number = 'EXP-'.now()->format('Y').'-'.str_pad((string) $expense->id, 5, '0', STR_PAD_LEFT);
        $expense->saveQuietly();

        $this->auditLogger->log(
            action: 'expense.created',
            auditable: $expense,
            newValues: $expense->only(['expense_number', 'category', 'amount', 'status']),
            tenantId: $expense->tenant_id,
        );
    }

    public function updated(Expense $expense): void
    {
        $this->auditLogger->log(
            action: 'expense.updated',
            auditable: $expense,
            oldValues: $expense->getOriginal(),
            newValues: $expense->getChanges(),
            tenantId: $expense->tenant_id,
        );
    }

    public function deleted(Expense $expense): void
    {
        $this->auditLogger->log(
            action: 'expense.deleted',
            auditable: $expense,
            oldValues: $expense->only(['expense_number']),
            tenantId: $expense->tenant_id,
        );
    }
}
