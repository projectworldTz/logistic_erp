<?php

namespace App\Observers;

use App\Models\PayrollRun;
use App\Services\Audit\AuditLogger;

class PayrollRunObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(PayrollRun $run): void
    {
        $this->auditLogger->log(
            action: 'payroll_run.created',
            auditable: $run,
            newValues: $run->only(['payroll_period_id', 'run_number', 'status']),
            tenantId: $run->tenant_id,
        );
    }

    public function updated(PayrollRun $run): void
    {
        if (! $run->wasChanged('status')) {
            return;
        }

        $this->auditLogger->log(
            action: 'payroll_run.status_changed',
            auditable: $run,
            oldValues: ['status' => $run->getOriginal('status')],
            newValues: ['status' => $run->status->value, 'total_net' => $run->total_net],
            tenantId: $run->tenant_id,
        );
    }
}
