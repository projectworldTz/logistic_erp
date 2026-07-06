<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UploadLandingImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'max:4096'],
            'purpose' => ['nullable', 'string', 'in:hero,avatar'],
        ];
    }
}
