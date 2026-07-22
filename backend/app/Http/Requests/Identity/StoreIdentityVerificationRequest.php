<?php

namespace App\Http\Requests\Identity;

use App\Enums\IdentityDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreIdentityVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', new Enum(IdentityDocumentType::class)],
            'identity_number' => ['required', 'string', 'max:100'],
            'country_code' => ['required', 'string', 'size:2'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
