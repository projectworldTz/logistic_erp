<?php

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

class SubmitIdentityManualReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'supporting_document_type' => ['nullable', 'string', 'max:100'],
            'supporting_document_number' => ['nullable', 'string', 'max:100'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
