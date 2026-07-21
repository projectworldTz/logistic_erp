<?php

namespace App\Http\Requests\Hr;

use App\Enums\PayrollCalculationMethod;
use App\Enums\PayrollPercentageBase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdatePayrollComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'calculation_method' => ['sometimes', new Enum(PayrollCalculationMethod::class)],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'percentage_base' => ['nullable', new Enum(PayrollPercentageBase::class)],
            'formula_notes' => ['nullable', 'string'],
            'is_taxable' => ['sometimes', 'boolean'],
            'is_pensionable' => ['sometimes', 'boolean'],
            'is_recurring' => ['sometimes', 'boolean'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'designation_category' => ['nullable', 'string', 'max:100'],
            'effective_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
