<?php

namespace App\Services\Hr;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\UserNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveRequestService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Approving deducts the leave balance for the request's year inside a
     * transaction — if no balance row exists yet, one is created with a
     * zero entitlement so the deduction still lands (and shows up as a
     * negative "available" figure HR can see and correct), rather than
     * silently failing to track it.
     */
    public function approve(LeaveRequest $leaveRequest): LeaveRequest
    {
        abort_if($leaveRequest->status !== LeaveRequestStatus::Pending, 409, 'Only pending leave requests can be approved.');

        DB::transaction(function () use ($leaveRequest) {
            $leaveRequest->update(['status' => LeaveRequestStatus::Approved, 'approved_by' => Auth::id()]);

            $balance = LeaveBalance::query()->firstOrCreate(
                [
                    'tenant_id' => $leaveRequest->tenant_id,
                    'employee_id' => $leaveRequest->employee_id,
                    'leave_type_id' => $leaveRequest->leave_type_id,
                    'year' => $leaveRequest->start_date->year,
                ],
                ['entitled_days' => 0, 'used_days' => 0, 'carried_forward_days' => 0],
            );

            $balance->increment('used_days', (float) $leaveRequest->days);
        });

        $this->auditLogger->log(
            'leave_request.approved', $leaveRequest,
            newValues: $leaveRequest->only(['employee_id', 'days']),
            tenantId: $leaveRequest->tenant_id,
        );

        $this->notifyCreator($leaveRequest, 'leave_request.approved', 'Leave request approved', 'Your leave request was approved.');

        return $leaveRequest;
    }

    public function reject(LeaveRequest $leaveRequest, string $reason): LeaveRequest
    {
        abort_if($leaveRequest->status !== LeaveRequestStatus::Pending, 409, 'Only pending leave requests can be rejected.');

        $leaveRequest->update([
            'status' => LeaveRequestStatus::Rejected,
            'approved_by' => Auth::id(),
            'rejection_reason' => $reason,
        ]);

        $this->auditLogger->log(
            'leave_request.rejected', $leaveRequest,
            newValues: ['rejection_reason' => $reason],
            tenantId: $leaveRequest->tenant_id,
        );

        $this->notifyCreator($leaveRequest, 'leave_request.rejected', 'Leave request rejected', "Your leave request was rejected: {$reason}");

        return $leaveRequest;
    }

    public function cancel(LeaveRequest $leaveRequest): LeaveRequest
    {
        abort_if(
            ! in_array($leaveRequest->status, [LeaveRequestStatus::Pending, LeaveRequestStatus::Approved], true),
            409,
            'Only pending or approved leave requests can be cancelled.',
        );

        DB::transaction(function () use ($leaveRequest) {
            if ($leaveRequest->status === LeaveRequestStatus::Approved) {
                LeaveBalance::query()
                    ->where('tenant_id', $leaveRequest->tenant_id)
                    ->where('employee_id', $leaveRequest->employee_id)
                    ->where('leave_type_id', $leaveRequest->leave_type_id)
                    ->where('year', $leaveRequest->start_date->year)
                    ->decrement('used_days', (float) $leaveRequest->days);
            }

            $leaveRequest->update(['status' => LeaveRequestStatus::Cancelled]);
        });

        $this->auditLogger->log('leave_request.cancelled', $leaveRequest, tenantId: $leaveRequest->tenant_id);

        return $leaveRequest;
    }

    private function notifyCreator(LeaveRequest $leaveRequest, string $type, string $title, string $message): void
    {
        if (! $leaveRequest->created_by || $leaveRequest->created_by === Auth::id()) {
            return;
        }

        $now = now();

        UserNotification::query()->insert([[
            'tenant_id' => $leaveRequest->tenant_id,
            'user_id' => $leaveRequest->created_by,
            'actor_id' => Auth::id(),
            'type' => $type,
            'notifiable_type' => $leaveRequest->getMorphClass(),
            'notifiable_id' => $leaveRequest->getKey(),
            'title' => $title,
            'message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ]]);
    }
}
