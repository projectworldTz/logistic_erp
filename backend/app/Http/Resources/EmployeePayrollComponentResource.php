<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeePayrollComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'payroll_component_id' => $this->payroll_component_id,
            'payroll_component' => new PayrollComponentResource($this->whenLoaded('payrollComponent')),
            'amount' => $this->amount,
            'percentage' => $this->percentage,
            'effective_date' => $this->effective_date,
            'end_date' => $this->end_date,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
