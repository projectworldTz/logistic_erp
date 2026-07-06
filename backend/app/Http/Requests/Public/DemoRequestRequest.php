<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class DemoRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'preferred_time' => ['nullable', 'string', 'max:255'],
        ];
    }
}
