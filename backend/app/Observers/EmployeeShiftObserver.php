<?php

namespace App\Observers;

use App\Models\EmployeeShift;
use App\Services\Audit\AuditLogger;

class EmployeeShiftObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(EmployeeShift $assignment): void
    {
        $this->auditLogger->log(
            'employee_shift.assigned', $assignment,
            newValues: $assignment->only(['employee_id', 'shift_id', 'effective_date']),
            tenantId: $assignment->tenant_id,
        );
    }

    public function deleted(EmployeeShift $assignment): void
    {
        $this->auditLogger->log(
            'employee_shift.unassigned', $assignment,
            oldValues: $assignment->only(['employee_id', 'shift_id']),
            tenantId: $assignment->tenant_id,
        );
    }
}
