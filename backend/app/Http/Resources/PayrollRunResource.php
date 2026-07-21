<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_period_id' => $this->payroll_period_id,
            'period' => new PayrollPeriodResource($this->whenLoaded('period')),
            'run_number' => $this->run_number,
            'status' => $this->status,
            'statutory_rule_set_id' => $this->statutory_rule_set_id,
            'total_gross' => $this->total_gross,
            'total_deductions' => $this->total_deductions,
            'total_net' => $this->total_net,
            'total_employer_contributions' => $this->total_employer_contributions,
            'total_employer_cost' => $this->total_employer_cost,
            'calculated_at' => $this->calculated_at,
            'approved_at' => $this->approved_at,
            'finalized_at' => $this->finalized_at,
            'journal_entry_id' => $this->journal_entry_id,
            'posted_at' => $this->posted_at,
            'payslip_count' => $this->when($this->relationLoaded('payslips'), fn () => $this->payslips->count()),
            'salary_payment_batch' => new SalaryPaymentBatchResource($this->whenLoaded('salaryPaymentBatch')),
            'employee_count' => $this->whenCounted('runEmployees'),
            'exception_count' => $this->when(
                $this->relationLoaded('runEmployees'),
                fn () => $this->runEmployees->where('status', 'exception')->count(),
            ),
            'run_employees' => PayrollRunEmployeeResource::collection($this->whenLoaded('runEmployees')),
            'latest_approval_request' => new ApprovalRequestResource($this->whenLoaded('latestApprovalRequest')),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
