<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class RegisterTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_code' => ['required', 'string', 'exists:plans,code'],

            'owner.name' => ['required', 'string', 'max:255'],
            'owner.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner.phone' => ['nullable', 'string', 'max:50'],
            'owner.password' => ['required', 'string', 'min:8'],

            'company.name' => ['required', 'string', 'max:255'],
            'company.registration_number' => ['nullable', 'string', 'max:100'],
            'company.tax_number' => ['nullable', 'string', 'max:100'],
            'company.country' => ['required', 'string', 'max:100'],
            'company.city' => ['required', 'string', 'max:100'],
            'company.address' => ['required', 'string', 'max:255'],
            'company.currency' => ['required', 'string', 'size:3'],
            'company.timezone' => ['required', 'string', 'max:100'],
            'company.industry' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
