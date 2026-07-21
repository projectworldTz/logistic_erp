<?php

namespace App\Http\Requests\Hr;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\PreferredPaymentMethod;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'designation_id' => ['nullable', Rule::exists('designations', 'id')->where('tenant_id', $tenantId)],
            'reporting_manager_id' => ['nullable', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],

            'name' => ['required_without:first_name', 'nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'alternative_phone' => ['nullable', 'string', 'max:50'],
            'residential_address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'national_id_number' => ['nullable', 'string', 'max:100'],

            'job_title' => ['nullable', 'string', 'max:255'],
            'employee_category' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['sometimes', new Enum(EmploymentType::class)],
            'status' => ['sometimes', new Enum(EmployeeStatus::class)],
            'hire_date' => ['required', 'date'],
            'confirmation_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'payroll_eligible' => ['sometimes', 'boolean'],
            'notice_period_days' => ['nullable', 'integer', 'min:0'],
            'salary' => ['nullable', 'numeric', 'min:0'],

            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_branch_name' => ['nullable', 'string', 'max:255'],
            'mobile_money_provider' => ['nullable', 'string', 'max:100'],
            'mobile_money_number' => ['nullable', 'string', 'max:50'],
            'preferred_payment_method' => ['sometimes', new Enum(PreferredPaymentMethod::class)],
            'pay_currency' => ['nullable', 'string', 'size:3'],

            'statutory_details' => ['nullable', 'array'],
            'statutory_details.*' => ['nullable', 'string', 'max:255'],

            'notes' => ['nullable', 'string'],
        ];
    }
}
