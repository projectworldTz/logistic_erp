<?php

namespace App\Http\Requests\Shipments;

use App\Enums\TrackingEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTrackingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', new Enum(TrackingEventType::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'is_customer_visible' => ['sometimes', 'boolean'],
        ];
    }
}
