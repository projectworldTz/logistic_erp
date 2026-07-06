<?php

namespace App\Http\Requests\Fleet;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'registration_number' => ['sometimes', 'string', 'max:20', Rule::unique('vehicles')->where('tenant_id', $tenantId)->ignore($this->route('vehicle'))],
            'vehicle_type' => ['sometimes', new Enum(VehicleType::class)],
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'capacity_kg' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', new Enum(VehicleStatus::class)],
            'assigned_driver' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'last_service_date' => ['nullable', 'date'],
            'next_service_due' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
