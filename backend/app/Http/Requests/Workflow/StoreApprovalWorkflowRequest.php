<?php

namespace App\Http\Requests\Workflow;

use App\Enums\WorkflowSubjectType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreApprovalWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'subject_type' => ['required', new Enum(WorkflowSubjectType::class)],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.approver_role' => ['required', Rule::exists('roles', 'name')->where('tenant_id', $tenantId)],
        ];
    }
}
