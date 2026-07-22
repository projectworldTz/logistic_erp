<?php

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

class DecideIdentityManualReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reviewer_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
