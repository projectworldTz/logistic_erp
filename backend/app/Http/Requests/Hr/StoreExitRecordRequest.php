<?php

namespace App\Http\Requests\Hr;

use App\Enums\ExitType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreExitRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
                Rule::unique('exit_records', 'employee_id')->where('tenant_id', $tenantId),
            ],
            'exit_type' => ['required', new Enum(ExitType::class)],
            'notice_date' => ['required', 'date'],
            'last_working_date' => ['required', 'date', 'after_or_equal:notice_date'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
