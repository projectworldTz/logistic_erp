<?php

namespace App\Services\Payroll;

use App\Enums\PayrollDeductionType;
use App\Enums\PayrollEarningSource;
use App\Enums\PayrollRunEmployeeStatus;
use App\Enums\PayrollRunStatus;
use App\Models\Employee;
use App\Models\EmployeePayrollComponent;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\PayrollSettings;
use App\Models\StatutoryRuleSet;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates a full payroll run calculation: for every eligible
 * employee, builds basic salary + assigned components + overtime as
 * earnings, unpaid-absence + assigned deduction components + statutory
 * tax/contributions as deductions, and statutory/employer-contribution
 * components as employer cost — then persists a snapshot per employee
 * (line items store the applied figures, not just a formula reference,
 * so history survives future rule changes).
 */
class PayrollCalculationService
{
    public function __construct(
        private readonly AttendancePayrollService $attendanceService,
        private readonly OvertimeCalculationService $overtimeService,
        private readonly PayrollValidationService $validationService,
        private readonly LoanDeductionService $loanDeductionService,
    ) {}

    public function calculate(PayrollRun $run): PayrollRun
    {
        abort_if(
            ! in_array($run->status, [PayrollRunStatus::Draft, PayrollRunStatus::Calculated], true),
            409,
            'Only draft or already-calculated runs can be (re)calculated.',
        );

        return DB::transaction(function () use ($run) {
            $period = $run->period;
            $settings = PayrollSettings::query()->firstOrCreate(['tenant_id' => $run->tenant_id]);
            if ($settings->wasRecentlyCreated) {
                $settings->refresh();
            }
            $ruleSet = $run->statutoryRuleSet ?: $settings->statutoryRuleSet;

            // Wipe any prior calculation for this run so recalculation is idempotent.
            $run->runEmployees()->each(function (PayrollRunEmployee $runEmployee) {
                $runEmployee->earnings()->delete();
                $runEmployee->deductions()->delete();
                $runEmployee->employerContributions()->delete();
                $runEmployee->delete();
            });

            $employees = Employee::query()
                ->where('tenant_id', $run->tenant_id)
                ->where('payroll_eligible', true)
                ->where('hire_date', '<=', $period->period_end)
                ->where(fn ($query) => $query->whereNull('termination_date')->orWhere('termination_date', '>=', $period->period_start))
                ->get();

            $totals = ['gross' => '0', 'deductions' => '0', 'net' => '0', 'employer_contributions' => '0', 'employer_cost' => '0'];

            foreach ($employees as $employee) {
                $result = $this->calculateForEmployee($run, $employee, $settings, $ruleSet);

                if ($result['runEmployee']->status === PayrollRunEmployeeStatus::Included) {
                    $totals['gross'] = PayrollMath::add($totals['gross'], (string) $result['runEmployee']->gross_pay);
                    $totals['deductions'] = PayrollMath::add($totals['deductions'], (string) $result['runEmployee']->total_deductions);
                    $totals['net'] = PayrollMath::add($totals['net'], (string) $result['runEmployee']->net_pay);
                    $totals['employer_contributions'] = PayrollMath::add($totals['employer_contributions'], (string) $result['runEmployee']->total_employer_contributions);
                }
            }

            $totals['employer_cost'] = PayrollMath::add($totals['gross'], $totals['employer_contributions']);

            $run->update([
                'status' => PayrollRunStatus::Calculated,
                'statutory_rule_set_id' => $ruleSet?->id,
                'total_gross' => PayrollMath::money($totals['gross']),
                'total_deductions' => PayrollMath::money($totals['deductions']),
                'total_net' => PayrollMath::money($totals['net']),
                'total_employer_contributions' => PayrollMath::money($totals['employer_contributions']),
                'total_employer_cost' => PayrollMath::money($totals['employer_cost']),
                'calculated_at' => now(),
            ]);

            return $run->fresh(['runEmployees.earnings', 'runEmployees.deductions', 'runEmployees.employerContributions']);
        });
    }

    /**
     * @return array{runEmployee: PayrollRunEmployee}
     */
    /**
     * Re-sums a run's totals from its already-calculated employee rows,
     * without re-running the calculation — used after an employee is
     * manually included/excluded post-calculation.
     */
    public function recomputeTotals(PayrollRun $run): PayrollRun
    {
        $included = $run->runEmployees()->where('status', PayrollRunEmployeeStatus::Included)->get();

        $gross = $included->reduce(fn (string $carry, PayrollRunEmployee $e) => PayrollMath::add($carry, (string) $e->gross_pay), '0');
        $deductions = $included->reduce(fn (string $carry, PayrollRunEmployee $e) => PayrollMath::add($carry, (string) $e->total_deductions), '0');
        $net = $included->reduce(fn (string $carry, PayrollRunEmployee $e) => PayrollMath::add($carry, (string) $e->net_pay), '0');
        $employerContributions = $included->reduce(fn (string $carry, PayrollRunEmployee $e) => PayrollMath::add($carry, (string) $e->total_employer_contributions), '0');

        $run->update([
            'total_gross' => PayrollMath::money($gross),
            'total_deductions' => PayrollMath::money($deductions),
            'total_net' => PayrollMath::money($net),
            'total_employer_contributions' => PayrollMath::money($employerContributions),
            'total_employer_cost' => PayrollMath::money(PayrollMath::add($gross, $employerContributions)),
        ]);

        return $run->fresh();
    }

    private function calculateForEmployee(PayrollRun $run, Employee $employee, PayrollSettings $settings, ?StatutoryRuleSet $ruleSet): array
    {
        $period = $run->period;
        $preconditions = $this->validationService->validatePreconditions($employee, $period);

        if (! empty($preconditions['blocking'])) {
            $runEmployee = PayrollRunEmployee::query()->create([
                'tenant_id' => $run->tenant_id,
                'payroll_run_id' => $run->id,
                'employee_id' => $employee->id,
                'basic_salary' => 0,
                'status' => PayrollRunEmployeeStatus::Exception,
                'exception_notes' => implode(' ', [...$preconditions['blocking'], ...$preconditions['warnings']]),
            ]);

            return ['runEmployee' => $runEmployee];
        }

        $activeContract = $employee->contracts()
            ->where('status', 'active')
            ->where('effective_date', '<=', $period->period_end)
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', $period->period_start))
            ->orderByDesc('effective_date')
            ->first();
        $basicSalary = (string) ($activeContract->basic_salary ?? $employee->salary);

        $runEmployee = PayrollRunEmployee::query()->create([
            'tenant_id' => $run->tenant_id,
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'basic_salary' => PayrollMath::money($basicSalary),
            'status' => PayrollRunEmployeeStatus::Included,
        ]);

        $earningsTotal = $basicSalary;
        $taxableEarnings = $basicSalary;
        $pensionableEarnings = $basicSalary;

        $runEmployee->earnings()->create([
            'tenant_id' => $run->tenant_id,
            'payroll_component_id' => null,
            'source' => PayrollEarningSource::Basic,
            'label' => 'Basic Salary',
            'amount' => PayrollMath::money($basicSalary),
            'is_taxable' => true,
            'is_pensionable' => true,
        ]);

        $assignments = EmployeePayrollComponent::query()
            ->where('tenant_id', $run->tenant_id)
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('effective_date', '<=', $period->period_end)
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $period->period_start))
            ->with('payrollComponent')
            ->get()
            ->filter(fn (EmployeePayrollComponent $assignment) => $assignment->payrollComponent
                && $assignment->payrollComponent->is_active
                && $assignment->payrollComponent->calculation_method !== \App\Enums\PayrollCalculationMethod::Formula)
            ->sortBy(fn (EmployeePayrollComponent $assignment) => $assignment->payrollComponent->sort_order);

        $earningComponents = $assignments->filter(fn ($a) => $a->payrollComponent->type === \App\Enums\PayrollComponentType::Earning);
        $deductionComponents = $assignments->filter(fn ($a) => $a->payrollComponent->type === \App\Enums\PayrollComponentType::Deduction);
        $employerComponents = $assignments->filter(fn ($a) => $a->payrollComponent->type === \App\Enums\PayrollComponentType::EmployerContribution);

        foreach ($earningComponents as $assignment) {
            $amount = $this->resolveComponentAmount($assignment, $basicSalary, $earningsTotal);
            $earningsTotal = PayrollMath::add($earningsTotal, $amount);
            if ($assignment->payrollComponent->is_taxable) {
                $taxableEarnings = PayrollMath::add($taxableEarnings, $amount);
            }
            if ($assignment->payrollComponent->is_pensionable) {
                $pensionableEarnings = PayrollMath::add($pensionableEarnings, $amount);
            }

            $runEmployee->earnings()->create([
                'tenant_id' => $run->tenant_id,
                'payroll_component_id' => $assignment->payroll_component_id,
                'source' => PayrollEarningSource::Component,
                'label' => $assignment->payrollComponent->name,
                'amount' => PayrollMath::money($amount),
                'is_taxable' => $assignment->payrollComponent->is_taxable,
                'is_pensionable' => $assignment->payrollComponent->is_pensionable,
            ]);
        }

        $overtimePay = $this->overtimeService->overtimePay($employee, $period, $basicSalary, $settings);
        if (PayrollMath::gt($overtimePay, '0')) {
            $earningsTotal = PayrollMath::add($earningsTotal, $overtimePay);
            $taxableEarnings = PayrollMath::add($taxableEarnings, $overtimePay);
            $pensionableEarnings = PayrollMath::add($pensionableEarnings, $overtimePay);

            $runEmployee->earnings()->create([
                'tenant_id' => $run->tenant_id,
                'payroll_component_id' => null,
                'source' => PayrollEarningSource::Overtime,
                'label' => 'Overtime Pay',
                'amount' => PayrollMath::money($overtimePay),
                'is_taxable' => true,
                'is_pensionable' => true,
            ]);
        }

        $deductionsTotal = '0';

        $absenceDays = $this->attendanceService->unpaidAbsenceDays($employee, $period);
        if ($absenceDays > 0) {
            $dailyRate = $this->attendanceService->dailyRate($basicSalary, $settings);
            $absenceAmount = PayrollMath::mul($dailyRate, (string) $absenceDays);
            $deductionsTotal = PayrollMath::add($deductionsTotal, $absenceAmount);
            $taxableEarnings = PayrollMath::max('0', PayrollMath::sub($taxableEarnings, $absenceAmount));
            $pensionableEarnings = PayrollMath::max('0', PayrollMath::sub($pensionableEarnings, $absenceAmount));

            $runEmployee->deductions()->create([
                'tenant_id' => $run->tenant_id,
                'type' => PayrollDeductionType::Absence,
                'label' => "Unpaid Absence ({$absenceDays} day" . ($absenceDays > 1 ? 's' : '') . ')',
                'amount' => PayrollMath::money($absenceAmount),
            ]);
        }

        foreach ($deductionComponents as $assignment) {
            $amount = $this->resolveComponentAmount($assignment, $basicSalary, $earningsTotal);
            $deductionsTotal = PayrollMath::add($deductionsTotal, $amount);

            $runEmployee->deductions()->create([
                'tenant_id' => $run->tenant_id,
                'payroll_component_id' => $assignment->payroll_component_id,
                'type' => PayrollDeductionType::Component,
                'label' => $assignment->payrollComponent->name,
                'amount' => PayrollMath::money($amount),
            ]);
        }

        foreach ($this->loanDeductionService->dueLoanInstallments($employee, $period) as $installment) {
            $deductionsTotal = PayrollMath::add($deductionsTotal, (string) $installment->amount);
            $runEmployee->deductions()->create([
                'tenant_id' => $run->tenant_id,
                'loan_schedule_id' => $installment->id,
                'type' => PayrollDeductionType::Loan,
                'label' => "Loan Repayment (installment {$installment->installment_number})",
                'amount' => PayrollMath::money((string) $installment->amount),
            ]);
        }

        foreach ($this->loanDeductionService->dueAdvanceInstallments($employee, $period) as $installment) {
            $deductionsTotal = PayrollMath::add($deductionsTotal, (string) $installment->amount);
            $runEmployee->deductions()->create([
                'tenant_id' => $run->tenant_id,
                'salary_advance_schedule_id' => $installment->id,
                'type' => PayrollDeductionType::SalaryAdvance,
                'label' => "Salary Advance Repayment (installment {$installment->installment_number})",
                'amount' => PayrollMath::money((string) $installment->amount),
            ]);
        }

        $employerContributionsTotal = '0';

        if ($ruleSet) {
            $tax = $this->calculateProgressiveTax($ruleSet, $taxableEarnings);
            if (PayrollMath::gt($tax, '0')) {
                $deductionsTotal = PayrollMath::add($deductionsTotal, $tax);
                $runEmployee->deductions()->create([
                    'tenant_id' => $run->tenant_id,
                    'type' => PayrollDeductionType::StatutoryTax,
                    'label' => "PAYE ({$ruleSet->name})",
                    'amount' => PayrollMath::money($tax),
                ]);
            }

            foreach ($ruleSet->contributionRules()->where('is_active', true)->get() as $rule) {
                $base = $pensionableEarnings;
                if ($rule->min_base) {
                    $base = PayrollMath::max($base, (string) $rule->min_base);
                }
                if ($rule->max_base) {
                    $base = PayrollMath::min($base, (string) $rule->max_base);
                }

                if ($rule->employee_rate) {
                    $employeeAmount = PayrollMath::percent($base, (string) $rule->employee_rate);
                    if (PayrollMath::gt($employeeAmount, '0')) {
                        $deductionsTotal = PayrollMath::add($deductionsTotal, $employeeAmount);
                        $runEmployee->deductions()->create([
                            'tenant_id' => $run->tenant_id,
                            'statutory_contribution_rule_id' => $rule->id,
                            'type' => PayrollDeductionType::StatutoryContribution,
                            'label' => "{$rule->name} (Employee)",
                            'amount' => PayrollMath::money($employeeAmount),
                        ]);
                    }
                }

                if ($rule->employer_rate) {
                    $employerAmount = PayrollMath::percent($base, (string) $rule->employer_rate);
                    if (PayrollMath::gt($employerAmount, '0')) {
                        $employerContributionsTotal = PayrollMath::add($employerContributionsTotal, $employerAmount);
                        $runEmployee->employerContributions()->create([
                            'tenant_id' => $run->tenant_id,
                            'statutory_contribution_rule_id' => $rule->id,
                            'label' => "{$rule->name} (Employer)",
                            'amount' => PayrollMath::money($employerAmount),
                        ]);
                    }
                }
            }
        }

        foreach ($employerComponents as $assignment) {
            $amount = $this->resolveComponentAmount($assignment, $basicSalary, $earningsTotal);
            $employerContributionsTotal = PayrollMath::add($employerContributionsTotal, $amount);

            $runEmployee->employerContributions()->create([
                'tenant_id' => $run->tenant_id,
                'payroll_component_id' => $assignment->payroll_component_id,
                'label' => $assignment->payrollComponent->name,
                'amount' => PayrollMath::money($amount),
            ]);
        }

        $netPay = PayrollMath::sub($earningsTotal, $deductionsTotal);
        $netPayIssues = $this->validationService->validateNetPay($netPay);

        $runEmployee->update([
            'gross_pay' => PayrollMath::money($earningsTotal),
            'total_deductions' => PayrollMath::money($deductionsTotal),
            'total_employer_contributions' => PayrollMath::money($employerContributionsTotal),
            'net_pay' => PayrollMath::money($netPay),
            'status' => empty($netPayIssues) ? PayrollRunEmployeeStatus::Included : PayrollRunEmployeeStatus::Exception,
            'exception_notes' => empty($netPayIssues)
                ? (empty($preconditions['warnings']) ? null : implode(' ', $preconditions['warnings']))
                : implode(' ', $netPayIssues),
        ]);

        return ['runEmployee' => $runEmployee->fresh()];
    }

    private function resolveComponentAmount(EmployeePayrollComponent $assignment, string $basicSalary, string $grossSoFar): string
    {
        $component = $assignment->payrollComponent;

        if ($component->calculation_method === \App\Enums\PayrollCalculationMethod::Fixed) {
            return (string) ($assignment->amount ?? $component->amount ?? '0');
        }

        $rate = (string) ($assignment->percentage ?? $component->percentage ?? '0');
        $base = $component->percentage_base === \App\Enums\PayrollPercentageBase::GrossPay ? $grossSoFar : $basicSalary;

        return PayrollMath::percent($base, $rate);
    }

    /**
     * Progressive band calculation: each band taxes only the slice of
     * income that falls within it, not the whole amount at that band's
     * rate.
     */
    private function calculateProgressiveTax(StatutoryRuleSet $ruleSet, string $taxableIncome): string
    {
        $tax = '0';

        foreach ($ruleSet->taxBands()->orderBy('band_order')->get() as $band) {
            $lower = (string) $band->lower_bound;
            $upper = $band->upper_bound !== null ? (string) $band->upper_bound : null;

            if (PayrollMath::gt($lower, $taxableIncome)) {
                continue;
            }

            $bandCeiling = $upper !== null ? PayrollMath::min($upper, $taxableIncome) : $taxableIncome;
            $amountInBand = PayrollMath::sub($bandCeiling, $lower);

            if (PayrollMath::gt($amountInBand, '0')) {
                $tax = PayrollMath::add($tax, PayrollMath::percent($amountInBand, (string) $band->rate));
            }
        }

        return $tax;
    }
}
