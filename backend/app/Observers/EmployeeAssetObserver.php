<?php

namespace App\Observers;

use App\Models\EmployeeAsset;
use App\Services\Audit\AuditLogger;

class EmployeeAssetObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(EmployeeAsset $asset): void
    {
        $this->auditLogger->log(
            action: 'employee_asset.assigned',
            auditable: $asset,
            newValues: $asset->only(['employee_id', 'asset_type', 'asset_name']),
            tenantId: $asset->tenant_id,
        );
    }

    public function updated(EmployeeAsset $asset): void
    {
        if (! $asset->wasChanged('status')) {
            return;
        }

        $this->auditLogger->log(
            action: 'employee_asset.status_changed',
            auditable: $asset,
            oldValues: ['status' => $asset->getOriginal('status')],
            newValues: ['status' => $asset->status->value],
            tenantId: $asset->tenant_id,
        );
    }
}
