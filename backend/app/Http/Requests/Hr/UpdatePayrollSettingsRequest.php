<?php

namespace App\Http\Requests\Hr;

use App\Enums\PayFrequency;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdatePayrollSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $accountRule = Rule::exists('accounts', 'id')->where('tenant_id', $tenantId);

        return [
            'statutory_rule_set_id' => ['nullable', Rule::exists('statutory_rule_sets', 'id')->where('tenant_id', $tenantId)],
            'default_pay_frequency' => ['sometimes', new Enum(PayFrequency::class)],
            'overtime_multiplier' => ['sometimes', 'numeric', 'min:1', 'max:10'],
            'standard_working_days_per_month' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'standard_hours_per_day' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'salary_expense_account_id' => ['nullable', $accountRule],
            'allowance_expense_account_id' => ['nullable', $accountRule],
            'overtime_expense_account_id' => ['nullable', $accountRule],
            'bonus_expense_account_id' => ['nullable', $accountRule],
            'employer_contribution_expense_account_id' => ['nullable', $accountRule],
            'payroll_payable_account_id' => ['nullable', $accountRule],
            'tax_payable_account_id' => ['nullable', $accountRule],
            'statutory_contributions_payable_account_id' => ['nullable', $accountRule],
            'loan_receivable_account_id' => ['nullable', $accountRule],
            'advance_receivable_account_id' => ['nullable', $accountRule],
            'other_deductions_payable_account_id' => ['nullable', $accountRule],
            'bank_cash_account_id' => ['nullable', $accountRule],
        ];
    }
}
