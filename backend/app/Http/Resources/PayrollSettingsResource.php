<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'statutory_rule_set_id' => $this->statutory_rule_set_id,
            'statutory_rule_set' => new StatutoryRuleSetResource($this->whenLoaded('statutoryRuleSet')),
            'default_pay_frequency' => $this->default_pay_frequency,
            'overtime_multiplier' => $this->overtime_multiplier,
            'standard_working_days_per_month' => $this->standard_working_days_per_month,
            'standard_hours_per_day' => $this->standard_hours_per_day,
            'salary_expense_account_id' => $this->salary_expense_account_id,
            'allowance_expense_account_id' => $this->allowance_expense_account_id,
            'overtime_expense_account_id' => $this->overtime_expense_account_id,
            'bonus_expense_account_id' => $this->bonus_expense_account_id,
            'employer_contribution_expense_account_id' => $this->employer_contribution_expense_account_id,
            'payroll_payable_account_id' => $this->payroll_payable_account_id,
            'tax_payable_account_id' => $this->tax_payable_account_id,
            'statutory_contributions_payable_account_id' => $this->statutory_contributions_payable_account_id,
            'loan_receivable_account_id' => $this->loan_receivable_account_id,
            'advance_receivable_account_id' => $this->advance_receivable_account_id,
            'other_deductions_payable_account_id' => $this->other_deductions_payable_account_id,
            'bank_cash_account_id' => $this->bank_cash_account_id,
            'created_at' => $this->created_at,
        ];
    }
}
