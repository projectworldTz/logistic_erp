<?php

namespace App\Observers;

use App\Models\PayrollComponent;
use App\Services\Audit\AuditLogger;

class PayrollComponentObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(PayrollComponent $component): void
    {
        $this->auditLogger->log(
            action: 'payroll_component.created',
            auditable: $component,
            newValues: $component->only(['code', 'name', 'type', 'calculation_method', 'amount', 'percentage']),
            tenantId: $component->tenant_id,
        );
    }

    public function updated(PayrollComponent $component): void
    {
        $this->auditLogger->log(
            action: 'payroll_component.updated',
            auditable: $component,
            oldValues: $component->getOriginal(),
            newValues: $component->getChanges(),
            tenantId: $component->tenant_id,
        );
    }

    public function deleted(PayrollComponent $component): void
    {
        $this->auditLogger->log(
            action: 'payroll_component.deleted',
            auditable: $component,
            oldValues: $component->only(['code', 'name']),
            tenantId: $component->tenant_id,
        );
    }
}
