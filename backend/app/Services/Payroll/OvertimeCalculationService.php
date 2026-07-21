<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollSettings;

/**
 * Overtime pay from *approved* OvertimeRequest hours within the period.
 * OvertimeRequest is the pre-approval object (requested, then approved by
 * a supervisor before the payroll run picks it up) — deliberately not
 * Timesheet.overtime_hours, which is just an informational log entered
 * after the fact and isn't itself approval-gated. The multiplier and
 * standard working-day/hour assumptions all come from PayrollSettings —
 * never hard-coded per the spec's instruction.
 */
class OvertimeCalculationService
{
    public function overtimeHours(Employee $employee, PayrollPeriod $period): string
    {
        return OvertimeRequest::query()
            ->where('tenant_id', $employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereBetween('date', [$period->period_start->toDateString(), $period->period_end->toDateString()])
            ->get(['hours'])
            ->reduce(fn (string $carry, OvertimeRequest $request) => PayrollMath::add($carry, (string) $request->hours), '0');
    }

    public function hourlyRate(string $basicSalary, PayrollSettings $settings): string
    {
        $monthlyHours = PayrollMath::mul(
            (string) max(1, $settings->standard_working_days_per_month),
            (string) max(1, $settings->standard_hours_per_day),
        );

        return PayrollMath::div($basicSalary, $monthlyHours);
    }

    public function overtimePay(Employee $employee, PayrollPeriod $period, string $basicSalary, PayrollSettings $settings): string
    {
        $hours = $this->overtimeHours($employee, $period);

        if (! PayrollMath::gt($hours, '0')) {
            return '0.0000';
        }

        $hourlyRate = $this->hourlyRate($basicSalary, $settings);
        $overtimeRate = PayrollMath::mul($hourlyRate, (string) $settings->overtime_multiplier);

        return PayrollMath::mul($hours, $overtimeRate);
    }
}
