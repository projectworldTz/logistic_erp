<?php

namespace App\Http\Requests\Hr;

use App\Enums\PayrollCalculationMethod;
use App\Enums\PayrollComponentType;
use App\Enums\PayrollPercentageBase;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StorePayrollComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('payroll_components', 'code')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(PayrollComponentType::class)],
            'calculation_method' => ['required', new Enum(PayrollCalculationMethod::class)],
            'amount' => ['nullable', 'numeric', 'min:0', 'required_if:calculation_method,fixed'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:calculation_method,percentage'],
            'percentage_base' => ['nullable', new Enum(PayrollPercentageBase::class), 'required_if:calculation_method,percentage'],
            'formula_notes' => ['nullable', 'string'],
            'is_taxable' => ['sometimes', 'boolean'],
            'is_pensionable' => ['sometimes', 'boolean'],
            'is_recurring' => ['sometimes', 'boolean'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'designation_category' => ['nullable', 'string', 'max:100'],
            'effective_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:effective_date'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
