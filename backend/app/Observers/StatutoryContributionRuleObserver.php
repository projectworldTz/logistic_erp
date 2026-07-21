<?php

namespace App\Observers;

use App\Models\StatutoryContributionRule;
use App\Services\Audit\AuditLogger;

class StatutoryContributionRuleObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(StatutoryContributionRule $rule): void
    {
        $this->auditLogger->log(
            action: 'statutory_contribution_rule.created',
            auditable: $rule,
            newValues: $rule->only(['statutory_rule_set_id', 'code', 'name', 'employee_rate', 'employer_rate']),
            tenantId: $rule->tenant_id,
        );
    }

    public function updated(StatutoryContributionRule $rule): void
    {
        $this->auditLogger->log(
            action: 'statutory_contribution_rule.updated',
            auditable: $rule,
            oldValues: $rule->getOriginal(),
            newValues: $rule->getChanges(),
            tenantId: $rule->tenant_id,
        );
    }

    public function deleted(StatutoryContributionRule $rule): void
    {
        $this->auditLogger->log(
            action: 'statutory_contribution_rule.deleted',
            auditable: $rule,
            oldValues: $rule->only(['statutory_rule_set_id', 'code', 'name']),
            tenantId: $rule->tenant_id,
        );
    }
}
