<?php

namespace App\Http\Requests\Hr;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMyLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * No employee_id field — self-service requests always target the
     * caller's own Employee record, resolved server-side in the controller.
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('tenant_id', $tenantId)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
