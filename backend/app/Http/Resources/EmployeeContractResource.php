<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'contract_number' => $this->contract_number,
            'employment_type' => $this->employment_type,
            'effective_date' => $this->effective_date,
            'expiry_date' => $this->expiry_date,
            'basic_salary' => $this->basic_salary,
            'pay_frequency' => $this->pay_frequency,
            'working_hours_per_week' => $this->working_hours_per_week,
            'workdays' => $this->workdays,
            'probation_period_days' => $this->probation_period_days,
            'notice_period_days' => $this->notice_period_days,
            'benefits' => $this->benefits,
            'overtime_eligible' => $this->overtime_eligible,
            'commission_eligible' => $this->commission_eligible,
            'leave_entitlement_days' => $this->leave_entitlement_days,
            'status' => $this->status,
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'approved_by' => new UserResource($this->whenLoaded('approver')),
            'renewed_from_contract_id' => $this->renewed_from_contract_id,
            'notes' => $this->notes,
            'approval_request' => $this->whenLoaded(
                'latestApprovalRequest',
                fn () => $this->latestApprovalRequest ? new ApprovalRequestResource($this->latestApprovalRequest) : null,
            ),
            'created_at' => $this->created_at,
        ];
    }
}
