<?php

namespace App\Http\Requests\Quotations;

use App\Enums\QuotationDirection;
use App\Enums\QuotationStatus;
use App\Enums\TransportMode;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateQuotationRequest extends FormRequest
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
            'direction' => ['sometimes', new Enum(QuotationDirection::class)],
            'mode' => ['sometimes', new Enum(TransportMode::class)],
            'origin_port' => ['nullable', 'string', 'max:255'],
            'destination_port' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['sometimes', 'date'],
            'valid_until' => ['sometimes', 'date', 'after_or_equal:issue_date'],
            'status' => ['sometimes', new Enum(QuotationStatus::class)],
            'subtotal' => ['sometimes', 'numeric', 'min:0'],
            'tax_amount' => ['sometimes', 'numeric', 'min:0'],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
