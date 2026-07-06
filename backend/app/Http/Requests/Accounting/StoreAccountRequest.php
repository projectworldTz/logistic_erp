<?php

namespace App\Http\Requests\Accounting;

use App\Enums\AccountType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(AccountType::class)],
            'parent_id' => ['nullable', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
