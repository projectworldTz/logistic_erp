<?php

namespace App\Http\Requests\Detention;

use App\Enums\ContainerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateDetentionRateCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'container_type' => ['nullable', new Enum(ContainerType::class)],
            'free_days' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'is_default' => ['sometimes', 'boolean'],
            'tiers' => ['sometimes', 'array', 'min:1'],
            'tiers.*.from_day' => ['required_with:tiers', 'integer', 'min:1'],
            'tiers.*.to_day' => ['nullable', 'integer', 'gte:tiers.*.from_day'],
            'tiers.*.daily_rate' => ['required_with:tiers', 'numeric', 'min:0'],
        ];
    }
}
