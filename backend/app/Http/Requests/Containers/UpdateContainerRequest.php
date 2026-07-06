<?php

namespace App\Http\Requests\Containers;

use App\Enums\ContainerStatus;
use App\Enums\ContainerType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateContainerRequest extends FormRequest
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
            'clearing_file_id' => ['nullable', Rule::exists('clearing_files', 'id')->where('tenant_id', $tenantId)],
            'freight_booking_id' => ['nullable', Rule::exists('freight_bookings', 'id')->where('tenant_id', $tenantId)],
            'container_number' => ['sometimes', 'string', 'max:20', Rule::unique('containers')->where('tenant_id', $tenantId)->ignore($this->route('container'))],
            'container_type' => ['sometimes', new Enum(ContainerType::class)],
            'seal_number' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', new Enum(ContainerStatus::class)],
            'gross_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'gate_in_date' => ['nullable', 'date'],
            'gate_out_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
