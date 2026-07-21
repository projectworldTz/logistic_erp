<?php

namespace App\Http\Requests\Hr;

use App\Enums\EmploymentType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreJobVacancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'title' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'designation_id' => ['nullable', Rule::exists('designations', 'id')->where('tenant_id', $tenantId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'employment_type' => ['nullable', new Enum(EmploymentType::class)],
            'number_of_openings' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'posted_date' => ['nullable', 'date'],
            'closing_date' => ['nullable', 'date', 'after_or_equal:posted_date'],
        ];
    }
}
