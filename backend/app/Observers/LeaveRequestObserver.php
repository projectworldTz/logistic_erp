<?php

namespace App\Observers;

use App\Models\LeaveRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class LeaveRequestObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(LeaveRequest $leaveRequest): void
    {
        $this->auditLogger->log(
            'leave_request.created', $leaveRequest,
            newValues: $leaveRequest->only(['employee_id', 'leave_type_id', 'start_date', 'end_date', 'days']),
            tenantId: $leaveRequest->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'hr.leave.approve', 'leave_request.submitted', 'Leave request awaiting approval',
            "A leave request for {$leaveRequest->start_date->toDateString()} to {$leaveRequest->end_date->toDateString()} needs approval.",
            $leaveRequest, Auth::id(),
        );
    }

    public function updated(LeaveRequest $leaveRequest): void
    {
        $this->auditLogger->log(
            'leave_request.updated', $leaveRequest,
            oldValues: $leaveRequest->getOriginal(), newValues: $leaveRequest->getChanges(),
            tenantId: $leaveRequest->tenant_id,
        );
    }

    public function deleted(LeaveRequest $leaveRequest): void
    {
        $this->auditLogger->log(
            'leave_request.deleted', $leaveRequest,
            oldValues: $leaveRequest->only(['employee_id', 'start_date', 'end_date']),
            tenantId: $leaveRequest->tenant_id,
        );
    }
}
