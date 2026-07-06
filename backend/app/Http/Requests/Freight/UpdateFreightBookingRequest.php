<?php

namespace App\Http\Requests\Freight;

use App\Enums\FreightDirection;
use App\Enums\FreightStatus;
use App\Enums\TransportMode;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateFreightBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', Rule::exists('customers', 'id')
                ->where('tenant_id', app(TenantContext::class)->id())],
            'direction' => ['sometimes', new Enum(FreightDirection::class)],
            'mode' => ['sometimes', new Enum(TransportMode::class)],
            'carrier' => ['nullable', 'string', 'max:255'],
            'vessel_flight_no' => ['nullable', 'string', 'max:255'],
            'booking_number' => ['nullable', 'string', 'max:255'],
            'origin_port' => ['nullable', 'string', 'max:255'],
            'destination_port' => ['nullable', 'string', 'max:255'],
            'cargo_description' => ['nullable', 'string'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'volume_cbm' => ['nullable', 'numeric', 'min:0'],
            'freight_charges' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', new Enum(FreightStatus::class)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')
                ->where('tenant_id', app(TenantContext::class)->id())],
            'etd' => ['nullable', 'date'],
            'eta' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
