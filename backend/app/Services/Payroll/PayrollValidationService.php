<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\PayrollPeriod;

/**
 * Splits payroll exceptions into blocking errors (the employee cannot be
 * paid this run until resolved — status becomes "exception") and
 * non-blocking warnings (surfaced for review but don't stop the run).
 */
class PayrollValidationService
{
    /**
     * @return array{blocking: string[], warnings: string[]}
     */
    public function validatePreconditions(Employee $employee, PayrollPeriod $period): array
    {
        $blocking = [];
        $warnings = [];

        if (! $employee->payroll_eligible) {
            $blocking[] = 'Employee is marked as not payroll-eligible.';
        }

        $activeContract = $employee->contracts()
            ->where('status', 'active')
            ->where('effective_date', '<=', $period->period_end)
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', $period->period_start))
            ->orderByDesc('effective_date')
            ->first();

        $basicSalary = $activeContract->basic_salary ?? $employee->salary;

        if (empty($basicSalary) || (float) $basicSalary <= 0) {
            $blocking[] = 'No basic salary is set (no active contract and no salary on the employee record).';
        }

        if ($employee->preferred_payment_method === \App\Enums\PreferredPaymentMethod::BankTransfer->value
            && empty($employee->bank_account_number)) {
            $blocking[] = 'Payment method is bank transfer but no bank account number is on file.';
        }

        if ($employee->preferred_payment_method === \App\Enums\PreferredPaymentMethod::MobileMoney->value
            && empty($employee->mobile_money_number)) {
            $blocking[] = 'Payment method is mobile money but no mobile money number is on file.';
        }

        if ($activeContract && $activeContract->expiry_date && $activeContract->expiry_date->lt($period->period_end)) {
            $warnings[] = 'Active contract expires before the end of this pay period.';
        }

        if (! $activeContract) {
            $warnings[] = 'No active employment contract found — falling back to the employee record salary.';
        }

        return ['blocking' => $blocking, 'warnings' => $warnings];
    }

    public function validateNetPay(string $netPay): array
    {
        return PayrollMath::gt('0', $netPay) ? ['Net pay is negative after deductions.'] : [];
    }
}
