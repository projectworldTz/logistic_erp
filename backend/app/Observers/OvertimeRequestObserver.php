<?php

namespace App\Observers;

use App\Models\OvertimeRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class OvertimeRequestObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(OvertimeRequest $overtimeRequest): void
    {
        $this->auditLogger->log(
            action: 'overtime_request.created',
            auditable: $overtimeRequest,
            newValues: $overtimeRequest->only(['employee_id', 'date', 'hours']),
            tenantId: $overtimeRequest->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'hr.overtime.approve', 'overtime_request.created', 'New overtime request',
            'An overtime request needs review.',
            $overtimeRequest, Auth::id(),
        );
    }

    public function updated(OvertimeRequest $overtimeRequest): void
    {
        $this->auditLogger->log(
            action: 'overtime_request.updated',
            auditable: $overtimeRequest,
            oldValues: $overtimeRequest->getOriginal(),
            newValues: $overtimeRequest->getChanges(),
            tenantId: $overtimeRequest->tenant_id,
        );
    }

    public function deleted(OvertimeRequest $overtimeRequest): void
    {
        $this->auditLogger->log(
            action: 'overtime_request.deleted',
            auditable: $overtimeRequest,
            tenantId: $overtimeRequest->tenant_id,
        );
    }
}
