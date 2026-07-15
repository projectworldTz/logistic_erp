<?php

namespace App\Http\Requests\Documents;

use App\Enums\DocumentCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'shipment_id' => ['nullable', Rule::exists('shipments', 'id')->where('tenant_id', $tenantId)],
            'category' => ['sometimes', new Enum(DocumentCategory::class)],
            'parent_document_id' => ['nullable', Rule::exists('documents', 'id')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string'],
        ];
    }
}
