<?php

namespace App\Observers;

use App\Models\SalaryPaymentBatch;
use App\Services\Audit\AuditLogger;

class SalaryPaymentBatchObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(SalaryPaymentBatch $batch): void
    {
        $batch->batch_number = 'PAYRUN-'.now()->format('Y').'-'.str_pad((string) $batch->id, 5, '0', STR_PAD_LEFT);
        $batch->saveQuietly();

        $this->auditLogger->log(
            action: 'salary_payment_batch.created',
            auditable: $batch,
            newValues: $batch->only(['payroll_run_id', 'batch_number']),
            tenantId: $batch->tenant_id,
        );
    }

    public function updated(SalaryPaymentBatch $batch): void
    {
        if (! $batch->wasChanged('status')) {
            return;
        }

        $this->auditLogger->log(
            action: 'salary_payment_batch.status_changed',
            auditable: $batch,
            oldValues: ['status' => $batch->getOriginal('status')],
            newValues: ['status' => $batch->status->value],
            tenantId: $batch->tenant_id,
        );
    }
}
