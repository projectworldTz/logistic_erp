<?php

namespace App\Http\Requests\Hr;

use App\Enums\PayFrequency;
use App\Models\PayrollPeriod;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'payment_date' => ['required', 'date'],
            'pay_frequency' => ['sometimes', new Enum(PayFrequency::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled(['period_start', 'period_end'])) {
                return;
            }

            $tenantId = app(TenantContext::class)->id();

            $exists = PayrollPeriod::query()
                ->where('tenant_id', $tenantId)
                ->whereDate('period_start', $this->input('period_start'))
                ->whereDate('period_end', $this->input('period_end'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('period_start', 'A payroll period already exists for these exact dates.');
            }
        });
    }
}
