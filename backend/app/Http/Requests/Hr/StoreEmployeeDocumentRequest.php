<?php

namespace App\Http\Requests\Hr;

use App\Enums\EmployeeDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', new Enum(EmployeeDocumentType::class)],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'notes' => ['nullable', 'string'],
            'parent_document_id' => ['nullable', 'integer', 'exists:employee_documents,id'],
        ];
    }
}
