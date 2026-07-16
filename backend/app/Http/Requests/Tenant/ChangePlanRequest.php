<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_code' => ['required', Rule::exists('plans', 'code')->where('is_active', true)],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
        ];
    }
}
