<?php

namespace App\Http\Requests\Hr;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreAttendanceRecordRequest extends FormRequest
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
            'shift_id' => ['nullable', Rule::exists('shifts', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
            'status' => ['sometimes', new Enum(AttendanceStatus::class)],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after_or_equal:check_in'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('employee_id') || ! $this->filled('date')) {
                return;
            }

            $exists = AttendanceRecord::query()
                ->where('employee_id', $this->input('employee_id'))
                ->whereDate('date', $this->input('date'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('date', 'An attendance record already exists for this employee on this date.');
            }
        });
    }
}
