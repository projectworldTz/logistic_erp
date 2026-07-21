<?php

namespace App\Observers;

use App\Models\PayrollSettings;
use App\Services\Audit\AuditLogger;

class PayrollSettingsObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(PayrollSettings $settings): void
    {
        $this->auditLogger->log(
            action: 'payroll_settings.created',
            auditable: $settings,
            newValues: $settings->getAttributes(),
            tenantId: $settings->tenant_id,
        );
    }

    public function updated(PayrollSettings $settings): void
    {
        $this->auditLogger->log(
            action: 'payroll_settings.updated',
            auditable: $settings,
            oldValues: $settings->getOriginal(),
            newValues: $settings->getChanges(),
            tenantId: $settings->tenant_id,
        );
    }
}
