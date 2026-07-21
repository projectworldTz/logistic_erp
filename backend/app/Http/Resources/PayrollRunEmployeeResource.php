<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollRunEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_run_id' => $this->payroll_run_id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'basic_salary' => $this->basic_salary,
            'gross_pay' => $this->gross_pay,
            'total_deductions' => $this->total_deductions,
            'total_employer_contributions' => $this->total_employer_contributions,
            'net_pay' => $this->net_pay,
            'status' => $this->status,
            'exception_notes' => $this->exception_notes,
            'earnings' => PayrollLineItemResource::collection($this->whenLoaded('earnings')),
            'deductions' => PayrollLineItemResource::collection($this->whenLoaded('deductions')),
            'employer_contributions' => PayrollLineItemResource::collection($this->whenLoaded('employerContributions')),
        ];
    }
}
