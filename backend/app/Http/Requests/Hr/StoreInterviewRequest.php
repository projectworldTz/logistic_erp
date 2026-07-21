<?php

namespace App\Http\Requests\Hr;

use App\Enums\InterviewMode;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'job_application_id' => ['required', Rule::exists('job_applications', 'id')->where('tenant_id', $tenantId)],
            'interviewer_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'scheduled_at' => ['required', 'date'],
            'mode' => ['sometimes', new Enum(InterviewMode::class)],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
