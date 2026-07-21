<?php

namespace App\Http\Requests\Hr;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStatutoryContributionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $ruleSetId = $this->route('statutoryRuleSet')?->id ?? $this->input('statutory_rule_set_id');

        return [
            'code' => [
                'required', 'string', 'max:100',
                Rule::unique('statutory_contribution_rules', 'code')
                    ->where('tenant_id', $tenantId)
                    ->where('statutory_rule_set_id', $ruleSetId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'employee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'employer_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_base' => ['nullable', 'numeric', 'min:0'],
            'max_base' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
