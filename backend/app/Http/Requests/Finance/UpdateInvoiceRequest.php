<?php

namespace App\Http\Requests\Finance;

use App\Enums\InvoiceStatus;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'customer_id' => ['sometimes', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'shipment_id' => ['nullable', Rule::exists('shipments', 'id')->where('tenant_id', $tenantId)],
            'issue_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date', 'after_or_equal:issue_date'],
            'status' => ['sometimes', new Enum(InvoiceStatus::class)],
            'subtotal' => ['sometimes', 'numeric', 'min:0'],
            'tax_amount' => ['sometimes', 'numeric', 'min:0'],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
