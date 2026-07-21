<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeLoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'loan_number' => $this->loan_number,
            'principal_amount' => $this->principal_amount,
            'interest_rate' => $this->interest_rate,
            'number_of_installments' => $this->number_of_installments,
            'installment_amount' => $this->installment_amount,
            'start_date' => $this->start_date,
            'status' => $this->status,
            'reason' => $this->reason,
            'disbursed_at' => $this->disbursed_at,
            'schedules' => LoanScheduleResource::collection($this->whenLoaded('schedules')),
            'approval_request' => new ApprovalRequestResource($this->whenLoaded('latestApprovalRequest')),
            'created_at' => $this->created_at,
        ];
    }
}
