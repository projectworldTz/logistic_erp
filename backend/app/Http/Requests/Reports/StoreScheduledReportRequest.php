<?php

namespace App\Http\Requests\Reports;

use App\Services\Reports\ReportBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduledReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'module' => ['required', Rule::in(array_keys(ReportBuilder::MODULE_PERMISSIONS))],
            'format' => ['required', Rule::in(['csv', 'xlsx'])],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['email'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
