<?php

namespace App\Observers;

use App\Models\Department;
use App\Services\Audit\AuditLogger;

class DepartmentObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(Department $department): void
    {
        $this->auditLogger->log(
            action: 'department.created',
            auditable: $department,
            newValues: $department->only(['name', 'branch_id']),
            tenantId: $department->tenant_id,
        );
    }

    public function updated(Department $department): void
    {
        $this->auditLogger->log(
            action: 'department.updated',
            auditable: $department,
            oldValues: $department->getOriginal(),
            newValues: $department->getChanges(),
            tenantId: $department->tenant_id,
        );
    }

    public function deleted(Department $department): void
    {
        $this->auditLogger->log(
            action: 'department.deleted',
            auditable: $department,
            oldValues: $department->only(['name']),
            tenantId: $department->tenant_id,
        );
    }
}
