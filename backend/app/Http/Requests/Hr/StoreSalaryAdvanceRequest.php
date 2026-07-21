<?php

namespace App\Http\Requests\Hr;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalaryAdvanceRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'number_of_installments' => ['required', 'integer', 'min:1', 'max:24'],
            'request_date' => ['required', 'date'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
