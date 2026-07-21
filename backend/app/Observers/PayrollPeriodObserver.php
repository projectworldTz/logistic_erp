<?php

namespace App\Observers;

use App\Models\PayrollPeriod;
use App\Services\Audit\AuditLogger;

class PayrollPeriodObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(PayrollPeriod $period): void
    {
        $this->auditLogger->log(
            action: 'payroll_period.created',
            auditable: $period,
            newValues: $period->only(['name', 'period_start', 'period_end', 'payment_date']),
            tenantId: $period->tenant_id,
        );
    }

    public function deleted(PayrollPeriod $period): void
    {
        $this->auditLogger->log(
            action: 'payroll_period.deleted',
            auditable: $period,
            oldValues: $period->only(['name']),
            tenantId: $period->tenant_id,
        );
    }
}
