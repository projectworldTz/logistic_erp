<?php

namespace App\Http\Requests\Hr;

use App\Enums\DisciplinaryCategory;
use App\Enums\DisciplinarySeverity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreDisciplinaryRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'incident_date' => ['required', 'date'],
            'category' => ['required', new Enum(DisciplinaryCategory::class)],
            'severity' => ['required', new Enum(DisciplinarySeverity::class)],
            'description' => ['required', 'string'],
            'action_taken' => ['nullable', 'string'],
        ];
    }
}
