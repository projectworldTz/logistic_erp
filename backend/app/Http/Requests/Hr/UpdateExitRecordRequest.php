<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExitRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exit_interview_notes' => ['nullable', 'string'],
            'assets_cleared' => ['sometimes', 'boolean'],
            'handover_completed' => ['sometimes', 'boolean'],
        ];
    }
}
