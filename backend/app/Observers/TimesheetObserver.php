<?php

namespace App\Observers;

use App\Models\Timesheet;
use App\Services\Audit\AuditLogger;

class TimesheetObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(Timesheet $timesheet): void
    {
        $this->auditLogger->log(
            'timesheet.created', $timesheet,
            newValues: $timesheet->only(['employee_id', 'date', 'total_hours']),
            tenantId: $timesheet->tenant_id,
        );
    }

    public function updated(Timesheet $timesheet): void
    {
        $this->auditLogger->log(
            'timesheet.updated', $timesheet,
            oldValues: $timesheet->getOriginal(), newValues: $timesheet->getChanges(),
            tenantId: $timesheet->tenant_id,
        );
    }

    public function deleted(Timesheet $timesheet): void
    {
        $this->auditLogger->log(
            'timesheet.deleted', $timesheet,
            oldValues: $timesheet->only(['employee_id', 'date']),
            tenantId: $timesheet->tenant_id,
        );
    }
}
