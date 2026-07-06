<?php

namespace App\Http\Requests\Warehouse;

use App\Enums\WarehouseItemStatus;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateWarehouseItemRequest extends FormRequest
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
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'description' => ['sometimes', 'string', 'max:255'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'bin_location' => ['nullable', 'string', 'max:255'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'volume_cbm' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', new Enum(WarehouseItemStatus::class)],
            'received_date' => ['nullable', 'date'],
            'dispatched_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
