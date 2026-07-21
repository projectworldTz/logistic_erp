<?php

namespace App\Http\Requests\Hr;

use App\Enums\DesignationCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $designationId = $this->route('designation')?->id;

        return [
            'name' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('designations')->where('tenant_id', $tenantId)->ignore($designationId),
            ],
            'category' => ['sometimes', new Enum(DesignationCategory::class)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
