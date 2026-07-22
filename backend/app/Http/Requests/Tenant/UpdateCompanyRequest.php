<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'string', 'max:100'],
            'city' => ['sometimes', 'string', 'max:100'],
            'address' => ['sometimes', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'in:USD,TZS'],
            'usd_to_tzs_rate' => ['sometimes', 'numeric', 'min:0.0001'],
            'timezone' => ['sometimes', 'string', 'max:100'],
            'industry' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'email_footer_text' => ['nullable', 'string', 'max:1000'],
            'email_reply_to' => ['nullable', 'email', 'max:255'],
            'notify_email_enabled' => ['sometimes', 'boolean'],
            'notify_sms_enabled' => ['sometimes', 'boolean'],
            'notify_whatsapp_enabled' => ['sometimes', 'boolean'],
            'require_identity_verification_before_payroll' => ['sometimes', 'boolean'],
        ];
    }
}
