<?php

namespace App\Http\Requests\Detention;

use Illuminate\Foundation\Http\FormRequest;

class WaiveDetentionChargeRequest extends FormRequest
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
