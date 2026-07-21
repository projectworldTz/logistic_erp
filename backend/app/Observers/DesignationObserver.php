<?php

namespace App\Observers;

use App\Models\Designation;
use App\Services\Audit\AuditLogger;

class DesignationObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(Designation $designation): void
    {
        $this->auditLogger->log(
            action: 'designation.created',
            auditable: $designation,
            newValues: $designation->only(['name', 'category']),
            tenantId: $designation->tenant_id,
        );
    }

    public function updated(Designation $designation): void
    {
        $this->auditLogger->log(
            action: 'designation.updated',
            auditable: $designation,
            oldValues: $designation->getOriginal(),
            newValues: $designation->getChanges(),
            tenantId: $designation->tenant_id,
        );
    }

    public function deleted(Designation $designation): void
    {
        $this->auditLogger->log(
            action: 'designation.deleted',
            auditable: $designation,
            oldValues: $designation->only(['name']),
            tenantId: $designation->tenant_id,
        );
    }
}
