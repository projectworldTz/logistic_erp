<?php

namespace App\Services\Payroll;

use App\Enums\PayrollDeductionType;
use App\Enums\PayrollEarningSource;
use App\Enums\PayrollRunEmployeeStatus;
use App\Models\PayrollDeduction;
use App\Models\PayrollEarning;
use App\Models\PayrollEmployerContribution;
use App\Models\PayrollRun;
use App\Models\PayrollSettings;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Support\Facades\DB;

/**
 * Builds one balanced JournalEntry per payroll run from PayrollSettings'
 * configurable chart-of-account mapping — no account numbers are ever
 * hard-coded, mirroring the plan's instruction. JournalEntryService
 * itself does not enforce debit=credit balance (that check lives in
 * StoreJournalEntryRequest for the manual-entry path), so this service
 * replicates it explicitly before persisting.
 *
 * Debit lines: salary expense (basic earnings net of unpaid-absence
 * deductions, which are never posted as a separate liability — they
 * simply reduce the expense), allowance expense (earning components),
 * overtime expense, employer-contribution expense.
 * Credit lines: payroll payable (net pay owed to staff), tax payable,
 * statutory contributions payable (employee + employer sides), loan
 * receivable, advance receivable, other-deductions payable.
 * Algebraically both sides always sum to (gross earnings - absence +
 * employer contributions) — verified by a dedicated balance test.
 */
class PayrollPostingService
{
    public function __construct(private readonly JournalEntryService $journalEntryService) {}

    public function post(PayrollRun $run): PayrollRun
    {
        abort_if($run->status->value !== 'approved' && $run->status->value !== 'finalized', 409, 'Only an approved or finalized run can be posted to accounting.');
        abort_if($run->journal_entry_id !== null, 409, 'This run has already been posted to accounting.');

        $settings = PayrollSettings::query()->where('tenant_id', $run->tenant_id)->first();
        abort_if(! $settings, 422, 'Payroll settings with chart-of-account mappings must be configured before posting.');

        return DB::transaction(function () use ($run, $settings) {
            $totals = $this->aggregateTotals($run);
            $this->validateAccountMappings($totals, $settings);
            $lines = $this->buildLines($totals, $settings);

            $totalDebit = array_sum(array_column($lines, 'debit'));
            $totalCredit = array_sum(array_column($lines, 'credit'));
            abort_unless(
                bccomp((string) round($totalDebit, 2), (string) round($totalCredit, 2), 2) === 0,
                500,
                'Payroll journal entry failed to balance — posting aborted.',
            );

            $entry = $this->journalEntryService->create([
                'entry_date' => now()->toDateString(),
                'description' => "Payroll run #{$run->run_number} — {$run->period->name}",
                'reference' => "PAYROLL-RUN-{$run->id}",
                'lines' => $lines,
            ]);
            $this->journalEntryService->post($entry);

            $run->update(['journal_entry_id' => $entry->id, 'posted_at' => now()]);

            return $run->fresh();
        });
    }

    /**
     * A non-zero category with no mapped account would otherwise be
     * silently dropped by addLine() and surface as an opaque "failed to
     * balance" error — check upfront so the message names the actual gap.
     *
     * @param  array<string, string>  $totals
     */
    private function validateAccountMappings(array $totals, PayrollSettings $settings): void
    {
        $requirements = [
            'basic' => ['salary_expense_account_id', 'Salary Expense Account'],
            'allowance' => ['allowance_expense_account_id', 'Allowance Expense Account'],
            'overtime' => ['overtime_expense_account_id', 'Overtime Expense Account'],
            'employer_contributions' => ['employer_contribution_expense_account_id', 'Employer Contribution Expense Account'],
            'net_pay' => ['payroll_payable_account_id', 'Payroll Payable Account'],
            'tax' => ['tax_payable_account_id', 'Tax Payable Account'],
            'loan' => ['loan_receivable_account_id', 'Loan Receivable Account'],
            'advance' => ['advance_receivable_account_id', 'Advance Receivable Account'],
            'other_deduction' => ['other_deductions_payable_account_id', 'Other Deductions Payable Account'],
        ];

        $missing = [];

        foreach ($requirements as $totalKey => [$settingField, $label]) {
            if (PayrollMath::gt($totals[$totalKey], '0') && ! $settings->{$settingField}) {
                $missing[] = $label;
            }
        }

        if (PayrollMath::gt(PayrollMath::add($totals['statutory_contribution'], $totals['employer_contributions']), '0')
            && ! $settings->statutory_contributions_payable_account_id) {
            $missing[] = 'Statutory Contributions Payable Account';
        }

        abort_if(
            ! empty($missing),
            422,
            'Cannot post to accounting — the following GL accounts are not mapped in Payroll Settings: '.implode(', ', array_unique($missing)),
        );
    }

    /**
     * @return array<string, string>
     */
    private function aggregateTotals(PayrollRun $run): array
    {
        $runEmployeeIds = $run->runEmployees()->where('status', PayrollRunEmployeeStatus::Included)->pluck('id');

        $earnings = PayrollEarning::query()
            ->whereIn('payroll_run_employee_id', $runEmployeeIds)
            ->selectRaw('source, COALESCE(SUM(amount), 0) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        $deductions = PayrollDeduction::query()
            ->whereIn('payroll_run_employee_id', $runEmployeeIds)
            ->selectRaw('type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $employerContributions = (string) PayrollEmployerContribution::query()
            ->whereIn('payroll_run_employee_id', $runEmployeeIds)
            ->sum('amount');

        return [
            'basic' => (string) ($earnings[PayrollEarningSource::Basic->value] ?? 0),
            'allowance' => (string) ($earnings[PayrollEarningSource::Component->value] ?? 0),
            'overtime' => (string) ($earnings[PayrollEarningSource::Overtime->value] ?? 0),
            'absence' => (string) ($deductions[PayrollDeductionType::Absence->value] ?? 0),
            'tax' => (string) ($deductions[PayrollDeductionType::StatutoryTax->value] ?? 0),
            'statutory_contribution' => (string) ($deductions[PayrollDeductionType::StatutoryContribution->value] ?? 0),
            'loan' => (string) ($deductions[PayrollDeductionType::Loan->value] ?? 0),
            'advance' => (string) ($deductions[PayrollDeductionType::SalaryAdvance->value] ?? 0),
            'other_deduction' => (string) PayrollMath::add(
                (string) ($deductions[PayrollDeductionType::Component->value] ?? 0),
                (string) ($deductions[PayrollDeductionType::Other->value] ?? 0),
            ),
            'employer_contributions' => $employerContributions,
            'net_pay' => (string) $run->total_net,
        ];
    }

    /**
     * @param  array<string, string>  $totals
     * @return array<int, array{account_id: int, debit: float, credit: float, description: string}>
     */
    private function buildLines(array $totals, PayrollSettings $settings): array
    {
        $lines = [];

        $salaryExpense = PayrollMath::money(PayrollMath::sub($totals['basic'], $totals['absence']));
        $this->addLine($lines, $settings->salary_expense_account_id, 'debit', $salaryExpense, 'Salary expense');
        $this->addLine($lines, $settings->allowance_expense_account_id, 'debit', $totals['allowance'], 'Allowance expense');
        $this->addLine($lines, $settings->overtime_expense_account_id, 'debit', $totals['overtime'], 'Overtime expense');
        $this->addLine($lines, $settings->employer_contribution_expense_account_id, 'debit', $totals['employer_contributions'], 'Employer contribution expense');

        $this->addLine($lines, $settings->payroll_payable_account_id, 'credit', $totals['net_pay'], 'Net pay payable to employees');
        $this->addLine($lines, $settings->tax_payable_account_id, 'credit', $totals['tax'], 'Statutory tax payable');
        $this->addLine(
            $lines,
            $settings->statutory_contributions_payable_account_id,
            'credit',
            PayrollMath::money(PayrollMath::add($totals['statutory_contribution'], $totals['employer_contributions'])),
            'Statutory contributions payable',
        );
        $this->addLine($lines, $settings->loan_receivable_account_id, 'credit', $totals['loan'], 'Loan repayments received');
        $this->addLine($lines, $settings->advance_receivable_account_id, 'credit', $totals['advance'], 'Salary advance repayments received');
        $this->addLine($lines, $settings->other_deductions_payable_account_id, 'credit', $totals['other_deduction'], 'Other deductions payable');

        return $lines;
    }

    private function addLine(array &$lines, ?int $accountId, string $side, string $amount, string $description): void
    {
        if (! $accountId || ! PayrollMath::gt($amount, '0')) {
            return;
        }

        $lines[] = [
            'account_id' => $accountId,
            'debit' => $side === 'debit' ? (float) $amount : 0,
            'credit' => $side === 'credit' ? (float) $amount : 0,
            'description' => $description,
        ];
    }
}
