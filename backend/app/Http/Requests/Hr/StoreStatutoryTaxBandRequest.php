<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreStatutoryTaxBandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lower_bound' => ['required', 'numeric', 'min:0'],
            'upper_bound' => ['nullable', 'numeric', 'gt:lower_bound'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'band_order' => ['required', 'integer', 'min:1'],
        ];
    }
}
