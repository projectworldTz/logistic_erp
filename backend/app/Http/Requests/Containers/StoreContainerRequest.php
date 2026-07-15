<?php

namespace App\Http\Requests\Containers;

use App\Enums\ContainerStatus;
use App\Enums\ContainerType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreContainerRequest extends FormRequest
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
            'clearing_file_id' => ['nullable', Rule::exists('clearing_files', 'id')->where('tenant_id', $tenantId)],
            'freight_booking_id' => ['nullable', Rule::exists('freight_bookings', 'id')->where('tenant_id', $tenantId)],
            'container_number' => ['required', 'string', 'max:20', Rule::unique('containers')->where('tenant_id', $tenantId)],
            'container_type' => ['required', new Enum(ContainerType::class)],
            'shipping_line' => ['nullable', 'string', 'max:255'],
            'vessel_name' => ['nullable', 'string', 'max:255'],
            'voyage_number' => ['nullable', 'string', 'max:255'],
            'port_of_loading' => ['nullable', 'string', 'max:255'],
            'port_of_discharge' => ['nullable', 'string', 'max:255'],
            'seal_number' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', new Enum(ContainerStatus::class)],
            'gross_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'gate_in_date' => ['nullable', 'date'],
            'eta' => ['nullable', 'date'],
            'ata' => ['nullable', 'date'],
            'gate_out_date' => ['nullable', 'date'],
            'empty_return_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
