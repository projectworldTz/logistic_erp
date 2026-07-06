<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Audit\AuditLogger;

class UserObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(User $user): void
    {
        $this->auditLogger->log(
            action: 'user.created',
            auditable: $user,
            newValues: $user->only(['name', 'email', 'status', 'is_super_admin']),
            tenantId: $user->tenant_id,
            userId: $user->id,
        );
    }

    public function updated(User $user): void
    {
        $this->auditLogger->log(
            action: 'user.updated',
            auditable: $user,
            oldValues: $user->getOriginal(),
            newValues: $user->getChanges(),
            tenantId: $user->tenant_id,
        );
    }

    public function deleted(User $user): void
    {
        $this->auditLogger->log(
            action: 'user.deleted',
            auditable: $user,
            oldValues: $user->only(['name', 'email']),
            tenantId: $user->tenant_id,
        );
    }
}
