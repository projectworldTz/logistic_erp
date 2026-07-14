<?php

namespace App\Http\Requests\Finance;

use App\Enums\ExpenseCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'shipment_id' => ['nullable', Rule::exists('shipments', 'id')->where('tenant_id', $tenantId)],
            'clearing_file_id' => ['nullable', Rule::exists('clearing_files', 'id')->where('tenant_id', $tenantId)],
            'freight_booking_id' => ['nullable', Rule::exists('freight_bookings', 'id')->where('tenant_id', $tenantId)],
            'category' => ['required', new Enum(ExpenseCategory::class)],
            'description' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'expense_date' => ['required', 'date'],
            'is_billable' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
