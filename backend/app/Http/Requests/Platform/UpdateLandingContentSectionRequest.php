<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateLandingContentSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $key = $this->route('key');
            $defaults = config("landing_content.{$key}");

            if ($defaults === null) {
                $validator->errors()->add('key', 'Unknown landing content section.');

                return;
            }

            $missing = array_diff(array_keys($defaults), array_keys($this->input('content', [])));

            if ($missing !== []) {
                $validator->errors()->add('content', 'Missing required field(s): '.implode(', ', $missing));
            }
        });
    }
}
