<?php

namespace App\Http\Requests\Fleet;

use App\Enums\VehicleLogType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreVehicleLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'type' => ['required', new Enum(VehicleLogType::class)],
            'log_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'odometer_km' => ['nullable', 'numeric', 'min:0'],
            'liters' => ['nullable', 'numeric', 'min:0'],
            'policy_number' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
            'driver_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'origin' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'distance_km' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
