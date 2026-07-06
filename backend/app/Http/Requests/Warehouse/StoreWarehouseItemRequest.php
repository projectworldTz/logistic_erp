<?php

namespace App\Http\Requests\Warehouse;

use App\Enums\WarehouseItemStatus;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreWarehouseItemRequest extends FormRequest
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
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0'],
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
