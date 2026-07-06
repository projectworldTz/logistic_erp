<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Deliberately no `exists:users` rule — that would leak account
        // existence via a 422 response to an unauthenticated caller.
        return [
            'email' => ['required', 'email'],
        ];
    }
}
