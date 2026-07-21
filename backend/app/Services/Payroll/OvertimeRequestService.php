<?php

namespace App\Services\Payroll;

use App\Enums\OvertimeRequestStatus;
use App\Models\OvertimeRequest;
use App\Models\UserNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;

class OvertimeRequestService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function approve(OvertimeRequest $overtimeRequest): OvertimeRequest
    {
        abort_if($overtimeRequest->status !== OvertimeRequestStatus::Pending, 409, 'Only pending overtime requests can be approved.');

        $overtimeRequest->update(['status' => OvertimeRequestStatus::Approved, 'approved_by' => Auth::id()]);

        $this->auditLogger->log(
            'overtime_request.approved', $overtimeRequest,
            newValues: $overtimeRequest->only(['employee_id', 'date', 'hours']),
            tenantId: $overtimeRequest->tenant_id,
        );

        $this->notifyEmployee($overtimeRequest, 'overtime_request.approved', 'Overtime request approved', 'Your overtime request was approved.');

        return $overtimeRequest;
    }

    public function reject(OvertimeRequest $overtimeRequest, string $reason): OvertimeRequest
    {
        abort_if($overtimeRequest->status !== OvertimeRequestStatus::Pending, 409, 'Only pending overtime requests can be rejected.');

        $overtimeRequest->update(['status' => OvertimeRequestStatus::Rejected, 'approved_by' => Auth::id()]);

        $this->auditLogger->log(
            'overtime_request.rejected', $overtimeRequest,
            newValues: ['rejection_reason' => $reason],
            tenantId: $overtimeRequest->tenant_id,
        );

        $this->notifyEmployee($overtimeRequest, 'overtime_request.rejected', 'Overtime request rejected', "Your overtime request was rejected: {$reason}");

        return $overtimeRequest;
    }

    private function notifyEmployee(OvertimeRequest $overtimeRequest, string $type, string $title, string $message): void
    {
        $userId = $overtimeRequest->employee?->user_id;

        if (! $userId || $userId === Auth::id()) {
            return;
        }

        $now = now();

        UserNotification::query()->insert([[
            'tenant_id' => $overtimeRequest->tenant_id,
            'user_id' => $userId,
            'actor_id' => Auth::id(),
            'type' => $type,
            'notifiable_type' => $overtimeRequest->getMorphClass(),
            'notifiable_id' => $overtimeRequest->getKey(),
            'title' => $title,
            'message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ]]);
    }
}
