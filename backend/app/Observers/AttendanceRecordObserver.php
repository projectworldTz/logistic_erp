<?php

namespace App\Observers;

use App\Models\AttendanceRecord;
use App\Services\Audit\AuditLogger;

class AttendanceRecordObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(AttendanceRecord $record): void
    {
        $this->auditLogger->log(
            action: 'attendance_record.created',
            auditable: $record,
            newValues: $record->only(['employee_id', 'date', 'status']),
            tenantId: $record->tenant_id,
        );
    }

    public function updated(AttendanceRecord $record): void
    {
        $this->auditLogger->log(
            action: 'attendance_record.updated',
            auditable: $record,
            oldValues: $record->getOriginal(),
            newValues: $record->getChanges(),
            tenantId: $record->tenant_id,
        );
    }

    public function deleted(AttendanceRecord $record): void
    {
        $this->auditLogger->log(
            action: 'attendance_record.deleted',
            auditable: $record,
            oldValues: $record->only(['employee_id', 'date']),
            tenantId: $record->tenant_id,
        );
    }
}
