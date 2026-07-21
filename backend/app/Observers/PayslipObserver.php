<?php

namespace App\Observers;

use App\Models\Payslip;
use App\Services\Audit\AuditLogger;

class PayslipObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(Payslip $payslip): void
    {
        $payslip->payslip_number = 'PAY-'.now()->format('Y').'-'.str_pad((string) $payslip->id, 6, '0', STR_PAD_LEFT);
        $payslip->saveQuietly();

        $this->auditLogger->log(
            action: 'payslip.generated',
            auditable: $payslip,
            newValues: $payslip->only(['employee_id', 'payslip_number', 'gross_pay', 'net_pay']),
            tenantId: $payslip->tenant_id,
        );
    }
}
