<?php

namespace App\Services\Payroll;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollSettings;

/**
 * Turns attendance records into a payroll-relevant unpaid-absence day
 * count. Weekend/holiday days are never counted against the employee —
 * only working days explicitly marked absent.
 */
class AttendancePayrollService
{
    public function unpaidAbsenceDays(Employee $employee, PayrollPeriod $period): int
    {
        return AttendanceRecord::query()
            ->where('tenant_id', $employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$period->period_start->toDateString(), $period->period_end->toDateString()])
            ->where('status', 'absent')
            ->where('is_weekend', false)
            ->where('is_holiday', false)
            ->count();
    }

    /**
     * Daily rate derived from basic salary and the tenant's configured
     * standard working days per month — not hard-coded.
     */
    public function dailyRate(string $basicSalary, PayrollSettings $settings): string
    {
        return PayrollMath::div($basicSalary, (string) max(1, $settings->standard_working_days_per_month));
    }
}
