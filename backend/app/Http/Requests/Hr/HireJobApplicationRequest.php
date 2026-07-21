<?php

namespace App\Http\Requests\Hr;

use App\Enums\EmploymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class HireJobApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employment_type' => ['nullable', new Enum(EmploymentType::class)],
            'hire_date' => ['nullable', 'date'],
        ];
    }
}
