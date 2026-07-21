<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExitRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'exit_type' => $this->exit_type,
            'notice_date' => $this->notice_date,
            'last_working_date' => $this->last_working_date,
            'reason' => $this->reason,
            'exit_interview_notes' => $this->exit_interview_notes,
            'status' => $this->status,
            'assets_cleared' => $this->assets_cleared,
            'handover_completed' => $this->handover_completed,
            'unused_leave_days' => $this->unused_leave_days,
            'leave_payout_amount' => $this->leave_payout_amount,
            'outstanding_loan_balance' => $this->outstanding_loan_balance,
            'outstanding_advance_balance' => $this->outstanding_advance_balance,
            'final_settlement_amount' => $this->final_settlement_amount,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
