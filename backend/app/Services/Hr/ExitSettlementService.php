<?php

namespace App\Services\Hr;

use App\Enums\ExitRecordStatus;
use App\Models\Employee;
use App\Models\ExitRecord;
use App\Models\LeaveBalance;
use App\Models\LoanSchedule;
use App\Models\PayrollSettings;
use App\Models\SalaryAdvanceSchedule;
use App\Services\Payroll\PayrollMath;

/**
 * Computes (but does not itself pay out) the figures HR needs to settle
 * an exiting employee: unused-leave payout, outstanding loan/advance
 * balances. This is a calculation and display step, not an automated
 * off-cycle payroll run — actually paying the settlement still goes
 * through the normal PayrollCalculationService/run workflow so it gets
 * the same approval chain and audit trail as every other payment.
 */
class ExitSettlementService
{
    public function computeAndStore(ExitRecord $exitRecord): ExitRecord
    {
        $employee = $exitRecord->employee;

        $unusedLeaveDays = $this->unusedLeaveDays($employee, $exitRecord->last_working_date->year);
        $dailyRate = $this->dailyRate($employee);
        $leavePayout = PayrollMath::money(PayrollMath::mul((string) $unusedLeaveDays, $dailyRate));

        $outstandingLoan = (string) LoanSchedule::query()
            ->where('tenant_id', $employee->tenant_id)
            ->whereHas('loan', fn ($query) => $query->where('employee_id', $employee->id)->where('status', 'active'))
            ->where('status', 'pending')
            ->sum('amount');

        $outstandingAdvance = (string) SalaryAdvanceSchedule::query()
            ->where('tenant_id', $employee->tenant_id)
            ->whereHas('advance', fn ($query) => $query->where('employee_id', $employee->id)->where('status', 'active'))
            ->where('status', 'pending')
            ->sum('amount');

        $finalSettlement = PayrollMath::money(
            PayrollMath::sub(PayrollMath::sub($leavePayout, $outstandingLoan), $outstandingAdvance)
        );

        $exitRecord->update([
            'unused_leave_days' => $unusedLeaveDays,
            'leave_payout_amount' => $leavePayout,
            'outstanding_loan_balance' => PayrollMath::money($outstandingLoan),
            'outstanding_advance_balance' => PayrollMath::money($outstandingAdvance),
            'final_settlement_amount' => $finalSettlement,
        ]);

        // refresh(), not fresh(): fresh() returns a new instance whose
        // wasRecentlyCreated defaults to false, which would silently turn
        // a 201 Created response from the store() controller into a 200.
        return $exitRecord->refresh();
    }

    private function unusedLeaveDays(Employee $employee, int $year): float
    {
        return (float) LeaveBalance::query()
            ->where('tenant_id', $employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->get()
            ->sum(fn (LeaveBalance $balance) => $balance->available_days);
    }

    private function dailyRate(Employee $employee): string
    {
        $activeContract = $employee->contracts()->where('status', 'active')->orderByDesc('effective_date')->first();
        $basicSalary = (string) ($activeContract->basic_salary ?? $employee->salary ?? 0);

        $settings = PayrollSettings::query()->where('tenant_id', $employee->tenant_id)->first();
        $workingDays = $settings->standard_working_days_per_month ?? 26;

        return PayrollMath::div($basicSalary, (string) max(1, $workingDays));
    }

    public function completeExit(ExitRecord $exitRecord): ExitRecord
    {
        abort_if($exitRecord->status === ExitRecordStatus::Completed, 409, 'This exit record is already completed.');
        abort_unless($exitRecord->assets_cleared && $exitRecord->handover_completed, 422, 'Assets must be cleared and handover completed before finalizing the exit.');

        $exitRecord->update(['status' => ExitRecordStatus::Completed, 'completed_at' => now()]);

        $exitRecord->employee->update([
            'status' => 'terminated',
            'termination_date' => $exitRecord->last_working_date,
            'payroll_eligible' => false,
        ]);

        return $exitRecord->fresh();
    }
}
