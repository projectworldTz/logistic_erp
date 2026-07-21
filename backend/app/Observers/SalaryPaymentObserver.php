<?php

namespace App\Observers;

use App\Models\SalaryPayment;
use App\Services\Audit\AuditLogger;

class SalaryPaymentObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function updated(SalaryPayment $payment): void
    {
        if (! $payment->wasChanged('status')) {
            return;
        }

        $this->auditLogger->log(
            action: 'salary_payment.status_changed',
            auditable: $payment,
            oldValues: ['status' => $payment->getOriginal('status')],
            newValues: ['status' => $payment->status->value, 'reference' => $payment->reference],
            tenantId: $payment->tenant_id,
        );
    }
}
