<?php

namespace App\Http\Requests\Shipments;

use Illuminate\Foundation\Http\FormRequest;

class StoreProofOfDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'received_by_name' => ['required', 'string', 'max:255'],
            'signature' => ['required', 'image', 'max:2048'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
