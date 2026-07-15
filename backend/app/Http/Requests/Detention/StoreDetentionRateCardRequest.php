<?php

namespace App\Http\Requests\Detention;

use App\Enums\ContainerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreDetentionRateCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'container_type' => ['nullable', new Enum(ContainerType::class)],
            'free_days' => ['required', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'is_default' => ['sometimes', 'boolean'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.from_day' => ['required', 'integer', 'min:1'],
            'tiers.*.to_day' => ['nullable', 'integer', 'gte:tiers.*.from_day'],
            'tiers.*.daily_rate' => ['required', 'numeric', 'min:0'],
        ];
    }
}
