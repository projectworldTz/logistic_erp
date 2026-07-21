<?php

namespace App\Models;

use App\Enums\PayFrequency;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSettings extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'statutory_rule_set_id',
        'default_pay_frequency',
        'overtime_multiplier',
        'standard_working_days_per_month',
        'standard_hours_per_day',
        'salary_expense_account_id',
        'allowance_expense_account_id',
        'overtime_expense_account_id',
        'bonus_expense_account_id',
        'employer_contribution_expense_account_id',
        'payroll_payable_account_id',
        'tax_payable_account_id',
        'statutory_contributions_payable_account_id',
        'loan_receivable_account_id',
        'advance_receivable_account_id',
        'other_deductions_payable_account_id',
        'bank_cash_account_id',
    ];

    protected $casts = [
        'default_pay_frequency' => PayFrequency::class,
        'overtime_multiplier' => 'decimal:2',
    ];

    public function statutoryRuleSet(): BelongsTo
    {
        return $this->belongsTo(StatutoryRuleSet::class);
    }

    public function salaryExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'salary_expense_account_id');
    }

    public function allowanceExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'allowance_expense_account_id');
    }

    public function overtimeExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'overtime_expense_account_id');
    }

    public function bonusExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bonus_expense_account_id');
    }

    public function employerContributionExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'employer_contribution_expense_account_id');
    }

    public function payrollPayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payroll_payable_account_id');
    }

    public function taxPayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'tax_payable_account_id');
    }

    public function statutoryContributionsPayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'statutory_contributions_payable_account_id');
    }

    public function loanReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'loan_receivable_account_id');
    }

    public function advanceReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'advance_receivable_account_id');
    }

    public function otherDeductionsPayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'other_deductions_payable_account_id');
    }

    public function bankCashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_cash_account_id');
    }
}
