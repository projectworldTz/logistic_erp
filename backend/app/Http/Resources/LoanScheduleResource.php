<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installment_number' => $this->installment_number,
            'due_date' => $this->due_date,
            'amount' => $this->amount,
            'status' => $this->status,
            'paid_in_payroll_run_id' => $this->paid_in_payroll_run_id,
        ];
    }
}
