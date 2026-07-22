<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class EmployeeIdentityManualReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'verification_id' => $this->verification_id,
            'status' => $this->status?->value,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'reviewer_notes' => $this->reviewer_notes,
            'supporting_document_type' => $this->supporting_document_type,
            'supporting_document_number' => $this->supporting_document_number,
            'download_url' => $this->supporting_document_path
                ? URL::temporarySignedRoute('identity-manual-reviews.download', now()->addMinutes(15), ['review' => $this->id])
                : null,
            'submitted_by' => $this->whenLoaded('submittedBy', fn () => $this->submittedBy?->name),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy?->name),
            'submitted_at' => $this->submitted_at,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
        ];
    }
}
