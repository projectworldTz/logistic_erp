<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billing_name' => ['sometimes', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
