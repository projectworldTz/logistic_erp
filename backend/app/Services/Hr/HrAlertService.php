<?php

namespace App\Services\Hr;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeDocument;
use App\Models\LoanSchedule;
use App\Models\PayrollPeriod;
use App\Models\SalaryAdvanceSchedule;
use App\Services\Notifications\NotificationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Real, scheduled HR alerts — every check reads live data (no synthetic
 * "reminder" table) and fans out through the existing NotificationService
 * so delivery (in-app/email/SMS/WhatsApp, per-tenant toggles) is the same
 * pipeline every other module already uses.
 */
class HrAlertService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array<string, int>
     */
    public function runDailyChecks(): array
    {
        return [
            'contract_expiry' => $this->contractExpiryReminders(),
            'document_expiry' => $this->documentExpiryReminders(),
            'missing_attendance' => $this->missingAttendanceAlerts(),
            'loan_installments_due' => $this->loanInstallmentReminders(),
            'payroll_period_due' => $this->payrollPeriodReminders(),
        ];
    }

    private function contractExpiryReminders(): int
    {
        $count = 0;
        $window = now()->addDays(30);

        EmployeeContract::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $window)
            ->whereDate('expiry_date', '>=', now())
            ->with('employee')
            ->get()
            ->each(function (EmployeeContract $contract) use (&$count) {
                $days = (int) now()->startOfDay()->diffInDays(Carbon::parse($contract->expiry_date)->startOfDay(), absolute: true);

                $this->notifications->notifyModuleUsers(
                    'hr.contracts.manage', 'employee_contract.expiring_soon', 'Contract expiring soon',
                    "Contract {$contract->contract_number} for {$contract->employee?->name} expires in {$days} day(s).",
                    $contract, null,
                );
                $count++;
            });

        return $count;
    }

    private function documentExpiryReminders(): int
    {
        $count = 0;
        $window = now()->addDays(30);

        EmployeeDocument::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('status', '!=', 'rejected')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $window)
            ->whereDate('expiry_date', '>=', now())
            ->with('employee')
            ->get()
            ->each(function (EmployeeDocument $document) use (&$count) {
                $days = (int) now()->startOfDay()->diffInDays(Carbon::parse($document->expiry_date)->startOfDay(), absolute: true);

                $this->notifications->notifyModuleUsers(
                    'hr.employees.documents.manage', 'employee_document.expiring_soon', 'Employee document expiring soon',
                    "{$document->document_type} for {$document->employee?->name} expires in {$days} day(s).",
                    $document, null,
                );
                $count++;
            });

        return $count;
    }

    /**
     * Flags employees with no attendance record for the previous working
     * day (skips weekends — a Monday check looks back to Friday).
     */
    private function missingAttendanceAlerts(): int
    {
        $checkDate = now()->subDay();
        while (in_array($checkDate->dayOfWeekIso, [6, 7], true)) {
            $checkDate->subDay();
        }

        $employeesWithRecords = AttendanceRecord::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->whereDate('date', $checkDate->toDateString())
            ->pluck('employee_id');

        $missing = Employee::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('payroll_eligible', true)
            ->where('status', '!=', 'terminated')
            ->whereDate('hire_date', '<=', $checkDate->toDateString())
            ->whereNotIn('id', $employeesWithRecords)
            ->count();

        $anchorEmployee = Employee::query()->where('tenant_id', $this->tenantContext->id())->first();

        if ($missing > 0 && $anchorEmployee) {
            $this->notifications->notifyModuleUsers(
                'hr.attendance.manage', 'attendance.missing_records', 'Missing attendance records',
                "{$missing} employee(s) have no attendance record for {$checkDate->toDateString()}.",
                $anchorEmployee,
                null,
            );
        }

        return $missing;
    }

    private function loanInstallmentReminders(): int
    {
        $window = now()->addDays(7);
        $count = 0;

        $dueLoans = LoanSchedule::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('status', 'pending')
            ->whereDate('due_date', '<=', $window)
            ->whereDate('due_date', '>=', now())
            ->count();

        $dueAdvances = SalaryAdvanceSchedule::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('status', 'pending')
            ->whereDate('due_date', '<=', $window)
            ->whereDate('due_date', '>=', now())
            ->count();

        $count = $dueLoans + $dueAdvances;

        if ($count > 0) {
            $employee = Employee::query()->where('tenant_id', $this->tenantContext->id())->first();
            if ($employee) {
                $this->notifications->notifyModuleUsers(
                    'hr.payroll_runs.manage', 'loan.installments_due', 'Loan/advance installments due soon',
                    "{$dueLoans} loan and {$dueAdvances} salary advance installment(s) are due within 7 days — they'll be deducted in the next payroll run.",
                    $employee, null,
                );
            }
        }

        return $count;
    }

    private function payrollPeriodReminders(): int
    {
        $window = now()->addDays(5);
        $count = 0;

        PayrollPeriod::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('is_locked', false)
            ->whereDate('payment_date', '<=', $window)
            ->whereDate('payment_date', '>=', now())
            ->whereDoesntHave('runs', fn ($query) => $query->where('status', 'finalized'))
            ->get()
            ->each(function (PayrollPeriod $period) use (&$count) {
                $this->notifications->notifyModuleUsers(
                    'hr.payroll_runs.manage', 'payroll_period.payment_due_soon', 'Payroll period payment date approaching',
                    "Payroll period \"{$period->name}\" has a payment date of {$period->payment_date->toDateString()} but no finalized run yet.",
                    $period, null,
                );
                $count++;
            });

        return $count;
    }
}
