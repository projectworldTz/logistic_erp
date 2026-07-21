<?php

namespace App\Observers;

use App\Models\StatutoryRuleSet;
use App\Services\Audit\AuditLogger;

class StatutoryRuleSetObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(StatutoryRuleSet $ruleSet): void
    {
        $this->auditLogger->log(
            action: 'statutory_rule_set.created',
            auditable: $ruleSet,
            newValues: $ruleSet->only(['name', 'country_code']),
            tenantId: $ruleSet->tenant_id,
        );
    }

    public function updated(StatutoryRuleSet $ruleSet): void
    {
        $this->auditLogger->log(
            action: 'statutory_rule_set.updated',
            auditable: $ruleSet,
            oldValues: $ruleSet->getOriginal(),
            newValues: $ruleSet->getChanges(),
            tenantId: $ruleSet->tenant_id,
        );
    }

    public function deleted(StatutoryRuleSet $ruleSet): void
    {
        $this->auditLogger->log(
            action: 'statutory_rule_set.deleted',
            auditable: $ruleSet,
            oldValues: $ruleSet->only(['name']),
            tenantId: $ruleSet->tenant_id,
        );
    }
}
