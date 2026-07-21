<?php

namespace App\Services\Payroll;

use App\Enums\LoanScheduleStatus;
use App\Models\Employee;
use App\Models\LoanSchedule;
use App\Models\PayrollPeriod;
use App\Models\SalaryAdvanceSchedule;
use Illuminate\Support\Collection;

/**
 * Finds loan/advance installments due within a payroll period. Selecting
 * due installments happens at calculate() time (so the amount shows up
 * in the run); actually marking them paid happens at finalize() time
 * (PayrollApprovalService::finalize()) so a discarded/recalculated draft
 * run never consumes an installment.
 */
class LoanDeductionService
{
    /**
     * @return Collection<int, LoanSchedule>
     */
    public function dueLoanInstallments(Employee $employee, PayrollPeriod $period): Collection
    {
        return LoanSchedule::query()
            ->where('tenant_id', $employee->tenant_id)
            ->whereHas('loan', fn ($query) => $query->where('employee_id', $employee->id)->where('status', 'active'))
            ->where('status', LoanScheduleStatus::Pending)
            ->whereDate('due_date', '<=', $period->period_end)
            ->get();
    }

    /**
     * @return Collection<int, SalaryAdvanceSchedule>
     */
    public function dueAdvanceInstallments(Employee $employee, PayrollPeriod $period): Collection
    {
        return SalaryAdvanceSchedule::query()
            ->where('tenant_id', $employee->tenant_id)
            ->whereHas('advance', fn ($query) => $query->where('employee_id', $employee->id)->where('status', 'active'))
            ->where('status', LoanScheduleStatus::Pending)
            ->whereDate('due_date', '<=', $period->period_end)
            ->get();
    }
}
