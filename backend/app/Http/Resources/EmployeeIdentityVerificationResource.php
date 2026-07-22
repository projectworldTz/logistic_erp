<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeIdentityVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeeDetails = $request->user()?->can('identity.verify') || $request->user()?->can('identity.confirm');

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'identity_document_type' => $this->identity_document_type?->value,
            'identity_number_masked' => $this->identity_number_masked,
            'identity_country_code' => $this->identity_country_code,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'status' => $this->verification_status?->value,
            'result_message' => $this->result_message,
            'failure_reason' => $this->failure_reason,
            'verified' => $this->verification_status?->value === 'verified',
            'person' => $canSeeDetails ? $this->response_metadata['person'] ?? null : null,
            'document' => $canSeeDetails ? $this->response_metadata['document'] ?? null : null,
            'requested_by' => $this->whenLoaded('requestedBy', fn () => $this->requestedBy?->name),
            'confirmed_by' => $this->whenLoaded('confirmedBy', fn () => $this->confirmedBy?->name),
            'rejected_by' => $this->whenLoaded('rejectedBy', fn () => $this->rejectedBy?->name),
            'requested_at' => $this->requested_at,
            'responded_at' => $this->responded_at,
            'confirmed_at' => $this->confirmed_at,
            'rejected_at' => $this->rejected_at,
            'created_at' => $this->created_at,
        ];
    }
}
