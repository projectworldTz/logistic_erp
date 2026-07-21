<?php

namespace App\Http\Requests\Hr;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('shifts')->where('tenant_id', $tenantId)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['sometimes', 'integer', 'min:0'],
            'grace_minutes' => ['sometimes', 'integer', 'min:0'],
            'overtime_threshold_hours' => ['nullable', 'numeric', 'min:0'],
            'night_allowance_amount' => ['nullable', 'numeric', 'min:0'],
            'weekend_rules' => ['nullable', 'array'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
