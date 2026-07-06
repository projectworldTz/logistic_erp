<?php

namespace App\Http\Requests\Accounting;

use App\Enums\AccountType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('accounts')->where('tenant_id', $tenantId)->ignore($this->route('account'))],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', new Enum(AccountType::class)],
            'parent_id' => ['nullable', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
