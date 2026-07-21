<?php

namespace App\Http\Requests\Hr;

use App\Enums\AttendanceStatus;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'shift_id' => ['nullable', Rule::exists('shifts', 'id')->where('tenant_id', $tenantId)],
            'status' => ['sometimes', new Enum(AttendanceStatus::class)],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after_or_equal:check_in'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
