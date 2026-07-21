<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnEmployeeAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'return_date' => ['required', 'date'],
            'condition_at_return' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['returned', 'lost', 'damaged'])],
        ];
    }
}
