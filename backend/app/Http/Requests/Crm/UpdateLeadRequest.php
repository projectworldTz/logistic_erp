<?php

namespace App\Http\Requests\Crm;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'source' => ['sometimes', new Enum(LeadSource::class)],
            'status' => ['sometimes', new Enum(LeadStatus::class)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')
                ->where('tenant_id', app(TenantContext::class)->id())],
            'notes' => ['nullable', 'string'],
        ];
    }
}
