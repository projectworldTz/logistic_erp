<?php

namespace App\Observers;

use App\Models\Account;
use App\Services\Audit\AuditLogger;

class AccountObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(Account $account): void
    {
        $this->auditLogger->log(
            action: 'account.created',
            auditable: $account,
            newValues: $account->only(['code', 'name', 'type']),
            tenantId: $account->tenant_id,
        );
    }

    public function updated(Account $account): void
    {
        $this->auditLogger->log(
            action: 'account.updated',
            auditable: $account,
            oldValues: $account->getOriginal(),
            newValues: $account->getChanges(),
            tenantId: $account->tenant_id,
        );
    }

    public function deleted(Account $account): void
    {
        $this->auditLogger->log(
            action: 'account.deleted',
            auditable: $account,
            oldValues: $account->only(['code', 'name']),
            tenantId: $account->tenant_id,
        );
    }
}
