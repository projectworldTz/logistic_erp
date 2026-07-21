<?php

namespace App\Http\Requests\Hr;

use App\Enums\EmploymentType;
use App\Enums\PayFrequency;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreEmployeeContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'employment_type' => ['required', new Enum(EmploymentType::class)],
            'effective_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:effective_date'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'pay_frequency' => ['sometimes', new Enum(PayFrequency::class)],
            'working_hours_per_week' => ['nullable', 'integer', 'min:1', 'max:168'],
            'workdays' => ['nullable', 'array'],
            'workdays.*' => ['string'],
            'probation_period_days' => ['nullable', 'integer', 'min:0'],
            'notice_period_days' => ['nullable', 'integer', 'min:0'],
            'benefits' => ['nullable', 'string'],
            'overtime_eligible' => ['sometimes', 'boolean'],
            'commission_eligible' => ['sometimes', 'boolean'],
            'leave_entitlement_days' => ['nullable', 'integer', 'min:0'],
            'renewed_from_contract_id' => ['nullable', Rule::exists('employee_contracts', 'id')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
