<?php

namespace App\Http\Requests\Hr;

use App\Enums\JobApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateJobApplicationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(JobApplicationStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
