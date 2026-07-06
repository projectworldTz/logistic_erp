<?php

namespace App\Http\Requests\Clearing;

use App\Enums\ClearingDirection;
use App\Enums\ClearingStatus;
use App\Enums\TransportMode;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreClearingFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')
                ->where('tenant_id', app(TenantContext::class)->id())],
            'direction' => ['required', new Enum(ClearingDirection::class)],
            'mode' => ['required', new Enum(TransportMode::class)],
            'port_of_loading' => ['nullable', 'string', 'max:255'],
            'port_of_discharge' => ['nullable', 'string', 'max:255'],
            'bl_awb_number' => ['nullable', 'string', 'max:255'],
            'customs_office' => ['nullable', 'string', 'max:255'],
            'declaration_number' => ['nullable', 'string', 'max:255'],
            'hs_code' => ['nullable', 'string', 'max:255'],
            'cargo_description' => ['nullable', 'string'],
            'status' => ['sometimes', new Enum(ClearingStatus::class)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')
                ->where('tenant_id', app(TenantContext::class)->id())],
            'duty_amount' => ['nullable', 'numeric', 'min:0'],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'eta' => ['nullable', 'date'],
            'cleared_date' => ['nullable', 'date'],
            'delivered_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
