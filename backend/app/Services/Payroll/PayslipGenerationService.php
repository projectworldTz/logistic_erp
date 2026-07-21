<?php

namespace App\Services\Payroll;

use App\Enums\PayrollRunEmployeeStatus;
use App\Models\Payslip;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates one payslip per included employee on a finalized run. YTD
 * figures are summed from every OTHER finalized run's payslip for the
 * same employee within the calendar year of the run's period end, so
 * they stay correct regardless of run order or mid-year onboarding.
 */
class PayslipGenerationService
{
    public function generateForRun(PayrollRun $run): void
    {
        DB::transaction(function () use ($run) {
            $year = $run->period->period_end->year;

            $run->runEmployees()
                ->where('status', PayrollRunEmployeeStatus::Included)
                ->with('employee')
                ->get()
                ->each(function (PayrollRunEmployee $runEmployee) use ($run, $year) {
                    if (Payslip::query()->where('payroll_run_employee_id', $runEmployee->id)->exists()) {
                        return;
                    }

                    $priorTotals = Payslip::query()
                        ->where('tenant_id', $run->tenant_id)
                        ->where('employee_id', $runEmployee->employee_id)
                        ->whereHas('payrollRun.period', fn ($query) => $query->whereYear('period_end', $year))
                        ->selectRaw('COALESCE(SUM(gross_pay), 0) as gross, COALESCE(SUM(total_deductions), 0) as deductions, COALESCE(SUM(net_pay), 0) as net')
                        ->first();

                    Payslip::query()->create([
                        'tenant_id' => $run->tenant_id,
                        'payroll_run_employee_id' => $runEmployee->id,
                        'employee_id' => $runEmployee->employee_id,
                        'payroll_run_id' => $run->id,
                        'gross_pay' => $runEmployee->gross_pay,
                        'total_deductions' => $runEmployee->total_deductions,
                        'net_pay' => $runEmployee->net_pay,
                        'total_employer_contributions' => $runEmployee->total_employer_contributions,
                        'ytd_gross' => PayrollMath::money(PayrollMath::add((string) $priorTotals->gross, (string) $runEmployee->gross_pay)),
                        'ytd_deductions' => PayrollMath::money(PayrollMath::add((string) $priorTotals->deductions, (string) $runEmployee->total_deductions)),
                        'ytd_net' => PayrollMath::money(PayrollMath::add((string) $priorTotals->net, (string) $runEmployee->net_pay)),
                        'verification_code' => Str::random(32),
                    ]);
                });
        });
    }
}
