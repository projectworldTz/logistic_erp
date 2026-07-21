<?php

namespace App\Http\Requests\Hr;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('leave_types')->where('tenant_id', $tenantId)],
            'is_paid' => ['sometimes', 'boolean'],
            'accrual_rule' => ['sometimes', 'string', 'in:none,monthly,annual'],
            'default_annual_days' => ['nullable', 'integer', 'min:0'],
            'carry_forward_max_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
