<?php

namespace App\Http\Requests\Demurrage;

use Illuminate\Foundation\Http\FormRequest;

class WaiveDemurrageChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
