<?php

namespace App\Services\Payroll;

use App\Enums\ApprovalRequestStatus;
use App\Enums\LoanScheduleStatus;
use App\Enums\LoanStatus;
use App\Enums\PayrollRunEmployeeStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\SalaryAdvanceStatus;
use App\Enums\WorkflowSubjectType;
use App\Models\PayrollDeduction;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Workflow\ApprovalEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollApprovalService
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly PayslipGenerationService $payslipService,
    ) {}

    public function submit(PayrollRun $run): PayrollRun
    {
        abort_if($run->status !== PayrollRunStatus::Calculated, 409, 'Only a calculated run can be submitted for approval.');
        abort_if(
            $run->runEmployees()->where('status', PayrollRunEmployeeStatus::Exception)->exists(),
            422,
            'Resolve all employee exceptions before submitting this run for approval.',
        );

        $run->update(['status' => PayrollRunStatus::PendingApproval]);
        $this->engine->start($run, WorkflowSubjectType::PayrollRun->value, (float) $run->total_net);

        return $run->fresh();
    }

    public function approve(PayrollRun $run): PayrollRun
    {
        abort_if($run->status !== PayrollRunStatus::PendingApproval, 409, 'Only a run pending approval can be approved.');

        $pending = $this->engine->findPendingRequestFor($run);

        if ($pending) {
            $decided = $this->engine->decide($pending, Auth::user(), true);

            if ($decided->status === ApprovalRequestStatus::Approved) {
                $run->update(['status' => PayrollRunStatus::Approved, 'approved_at' => now()]);
            }
        } else {
            abort_unless(Auth::user()?->can('hr.payroll_runs.approve'), 403);
            $run->update(['status' => PayrollRunStatus::Approved, 'approved_at' => now()]);
        }

        return $run->fresh();
    }

    public function reject(PayrollRun $run, ?string $reason): PayrollRun
    {
        abort_if($run->status !== PayrollRunStatus::PendingApproval, 409, 'Only a run pending approval can be rejected.');

        $pending = $this->engine->findPendingRequestFor($run);

        if ($pending) {
            $this->engine->decide($pending, Auth::user(), false, $reason);
        } else {
            abort_unless(Auth::user()?->can('hr.payroll_runs.approve'), 403);
        }

        $run->update(['status' => PayrollRunStatus::Rejected]);

        return $run->fresh();
    }

    public function finalize(PayrollRun $run): PayrollRun
    {
        abort_if($run->status !== PayrollRunStatus::Approved, 409, 'Only an approved run can be finalized.');

        DB::transaction(function () use ($run) {
            $run->update(['status' => PayrollRunStatus::Finalized, 'finalized_at' => now()]);
            $run->period->update(['is_locked' => true]);
            $this->settleLoanAndAdvanceInstallments($run);
            $this->payslipService->generateForRun($run);
        });

        return $run->fresh();
    }

    /**
     * Only at finalize — the irreversible point — do we mark loan/advance
     * installments as paid and reference which run paid them. A
     * discarded or recalculated draft run must never consume an
     * installment, which is why this doesn't happen at calculate() time.
     */
    private function settleLoanAndAdvanceInstallments(PayrollRun $run): void
    {
        $includedRunEmployeeIds = $run->runEmployees()->where('status', PayrollRunEmployeeStatus::Included)->pluck('id');

        $deductions = PayrollDeduction::query()
            ->whereIn('payroll_run_employee_id', $includedRunEmployeeIds)
            ->where(fn ($query) => $query->whereNotNull('loan_schedule_id')->orWhereNotNull('salary_advance_schedule_id'))
            ->with(['loanSchedule.loan', 'salaryAdvanceSchedule.advance'])
            ->get();

        foreach ($deductions as $deduction) {
            if ($deduction->loanSchedule) {
                $schedule = $deduction->loanSchedule;
                $schedule->update(['status' => LoanScheduleStatus::Paid, 'paid_in_payroll_run_id' => $run->id]);

                $loan = $schedule->loan;
                if (! $loan->schedules()->where('status', '!=', LoanScheduleStatus::Paid)->exists()) {
                    $loan->update(['status' => LoanStatus::Completed]);
                }
            }

            if ($deduction->salaryAdvanceSchedule) {
                $schedule = $deduction->salaryAdvanceSchedule;
                $schedule->update(['status' => LoanScheduleStatus::Paid, 'paid_in_payroll_run_id' => $run->id]);

                $advance = $schedule->advance;
                if (! $advance->schedules()->where('status', '!=', LoanScheduleStatus::Paid)->exists()) {
                    $advance->update(['status' => SalaryAdvanceStatus::Completed]);
                }
            }
        }
    }
}
