<?php

namespace App\Observers;

use App\Models\EmployeePayrollComponent;
use App\Services\Audit\AuditLogger;

class EmployeePayrollComponentObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(EmployeePayrollComponent $assignment): void
    {
        $this->auditLogger->log(
            action: 'employee_payroll_component.created',
            auditable: $assignment,
            newValues: $assignment->only(['employee_id', 'payroll_component_id', 'amount', 'percentage']),
            tenantId: $assignment->tenant_id,
        );
    }

    public function updated(EmployeePayrollComponent $assignment): void
    {
        $this->auditLogger->log(
            action: 'employee_payroll_component.updated',
            auditable: $assignment,
            oldValues: $assignment->getOriginal(),
            newValues: $assignment->getChanges(),
            tenantId: $assignment->tenant_id,
        );
    }

    public function deleted(EmployeePayrollComponent $assignment): void
    {
        $this->auditLogger->log(
            action: 'employee_payroll_component.deleted',
            auditable: $assignment,
            oldValues: $assignment->only(['employee_id', 'payroll_component_id']),
            tenantId: $assignment->tenant_id,
        );
    }
}
