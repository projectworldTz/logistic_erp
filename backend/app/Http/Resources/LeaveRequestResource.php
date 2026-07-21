<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'leave_type_id' => $this->leave_type_id,
            'leave_type' => new LeaveTypeResource($this->whenLoaded('leaveType')),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'days' => $this->days,
            'half_day' => $this->half_day,
            'reason' => $this->reason,
            'status' => $this->status,
            'attachment_path' => $this->attachment_path,
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'approved_by' => new UserResource($this->whenLoaded('approver')),
            'rejection_reason' => $this->rejection_reason,
            'approval_request' => $this->whenLoaded(
                'latestApprovalRequest',
                fn () => $this->latestApprovalRequest ? new ApprovalRequestResource($this->latestApprovalRequest) : null,
            ),
            'created_at' => $this->created_at,
        ];
    }
}
