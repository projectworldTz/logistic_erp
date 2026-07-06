<?php

namespace App\Http\Requests\Quotations;

use App\Enums\QuotationDirection;
use App\Enums\QuotationStatus;
use App\Enums\TransportMode;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'direction' => ['required', new Enum(QuotationDirection::class)],
            'mode' => ['required', new Enum(TransportMode::class)],
            'origin_port' => ['nullable', 'string', 'max:255'],
            'destination_port' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:issue_date'],
            'status' => ['sometimes', new Enum(QuotationStatus::class)],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['sometimes', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
