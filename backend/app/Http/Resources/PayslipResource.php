<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'payroll_run_id' => $this->payroll_run_id,
            'payslip_number' => $this->payslip_number,
            'gross_pay' => $this->gross_pay,
            'total_deductions' => $this->total_deductions,
            'net_pay' => $this->net_pay,
            'total_employer_contributions' => $this->total_employer_contributions,
            'ytd_gross' => $this->ytd_gross,
            'ytd_deductions' => $this->ytd_deductions,
            'ytd_net' => $this->ytd_net,
            'period' => $this->whenLoaded('payrollRun', fn () => [
                'name' => $this->payrollRun->period?->name,
                'period_start' => $this->payrollRun->period?->period_start,
                'period_end' => $this->payrollRun->period?->period_end,
                'payment_date' => $this->payrollRun->period?->payment_date,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
