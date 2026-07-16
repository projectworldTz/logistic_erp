<?php

namespace App\Http\Requests\Reports;

use App\Services\Reports\ReportBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduledReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'module' => ['sometimes', Rule::in(array_keys(ReportBuilder::MODULE_PERMISSIONS))],
            'format' => ['sometimes', Rule::in(['csv', 'xlsx'])],
            'frequency' => ['sometimes', Rule::in(['daily', 'weekly', 'monthly'])],
            'recipients' => ['sometimes', 'array', 'min:1'],
            'recipients.*' => ['email'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
