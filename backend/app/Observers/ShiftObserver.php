<?php

namespace App\Observers;

use App\Models\Shift;
use App\Services\Audit\AuditLogger;

class ShiftObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(Shift $shift): void
    {
        $this->auditLogger->log('shift.created', $shift, newValues: $shift->only(['name', 'start_time', 'end_time']), tenantId: $shift->tenant_id);
    }

    public function updated(Shift $shift): void
    {
        $this->auditLogger->log('shift.updated', $shift, oldValues: $shift->getOriginal(), newValues: $shift->getChanges(), tenantId: $shift->tenant_id);
    }

    public function deleted(Shift $shift): void
    {
        $this->auditLogger->log('shift.deleted', $shift, oldValues: $shift->only(['name']), tenantId: $shift->tenant_id);
    }
}
