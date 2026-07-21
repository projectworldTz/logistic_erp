<?php

namespace App\Observers;

use App\Models\LeaveType;
use App\Services\Audit\AuditLogger;

class LeaveTypeObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(LeaveType $leaveType): void
    {
        $this->auditLogger->log('leave_type.created', $leaveType, newValues: $leaveType->only(['name', 'is_paid']), tenantId: $leaveType->tenant_id);
    }

    public function updated(LeaveType $leaveType): void
    {
        $this->auditLogger->log('leave_type.updated', $leaveType, oldValues: $leaveType->getOriginal(), newValues: $leaveType->getChanges(), tenantId: $leaveType->tenant_id);
    }

    public function deleted(LeaveType $leaveType): void
    {
        $this->auditLogger->log('leave_type.deleted', $leaveType, oldValues: $leaveType->only(['name']), tenantId: $leaveType->tenant_id);
    }
}
