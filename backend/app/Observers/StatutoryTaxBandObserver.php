<?php

namespace App\Observers;

use App\Models\StatutoryTaxBand;
use App\Services\Audit\AuditLogger;

class StatutoryTaxBandObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(StatutoryTaxBand $band): void
    {
        $this->auditLogger->log(
            action: 'statutory_tax_band.created',
            auditable: $band,
            newValues: $band->only(['statutory_rule_set_id', 'lower_bound', 'upper_bound', 'rate']),
            tenantId: $band->tenant_id,
        );
    }

    public function updated(StatutoryTaxBand $band): void
    {
        $this->auditLogger->log(
            action: 'statutory_tax_band.updated',
            auditable: $band,
            oldValues: $band->getOriginal(),
            newValues: $band->getChanges(),
            tenantId: $band->tenant_id,
        );
    }

    public function deleted(StatutoryTaxBand $band): void
    {
        $this->auditLogger->log(
            action: 'statutory_tax_band.deleted',
            auditable: $band,
            oldValues: $band->only(['statutory_rule_set_id', 'lower_bound', 'upper_bound', 'rate']),
            tenantId: $band->tenant_id,
        );
    }
}
