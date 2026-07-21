<?php

namespace App\Http\Requests\Hr;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'job_vacancy_id' => [
                'required',
                Rule::exists('job_vacancies', 'id')->where('tenant_id', $tenantId),
                Rule::unique('job_applications')->where('tenant_id', $tenantId)->where('candidate_id', $this->input('candidate_id')),
            ],
            'candidate_id' => ['required', Rule::exists('candidates', 'id')->where('tenant_id', $tenantId)],
            'applied_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
