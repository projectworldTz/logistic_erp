<?php

namespace App\Http\Requests\Shipments;

use App\Enums\ShipmentDirection;
use App\Enums\ShipmentStatus;
use App\Enums\TransportMode;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreShipmentRequest extends FormRequest
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
            'quotation_id' => ['nullable', Rule::exists('quotations', 'id')->where('tenant_id', $tenantId)],
            'clearing_file_id' => ['nullable', Rule::exists('clearing_files', 'id')->where('tenant_id', $tenantId)],
            'freight_booking_id' => ['nullable', Rule::exists('freight_bookings', 'id')->where('tenant_id', $tenantId)],
            'direction' => ['required', new Enum(ShipmentDirection::class)],
            'mode' => ['required', new Enum(TransportMode::class)],
            'origin_port' => ['nullable', 'string', 'max:255'],
            'destination_port' => ['nullable', 'string', 'max:255'],
            'bl_awb_number' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', new Enum(ShipmentStatus::class)],
            'etd' => ['nullable', 'date'],
            'eta' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
