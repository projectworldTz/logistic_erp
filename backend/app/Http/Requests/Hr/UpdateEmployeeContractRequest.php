<?php

namespace App\Http\Requests\Hr;

use App\Enums\EmploymentType;
use App\Enums\PayFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateEmployeeContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employment_type' => ['sometimes', new Enum(EmploymentType::class)],
            'effective_date' => ['sometimes', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:effective_date'],
            'basic_salary' => ['sometimes', 'numeric', 'min:0'],
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
            'notes' => ['nullable', 'string'],
        ];
    }
}
