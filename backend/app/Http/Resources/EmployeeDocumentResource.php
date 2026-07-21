<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class EmployeeDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'document_type' => $this->document_type,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'issue_date' => $this->issue_date,
            'expiry_date' => $this->expiry_date,
            'status' => $this->computedStatus(),
            'notes' => $this->notes,
            'uploaded_by' => new UserResource($this->whenLoaded('uploadedBy')),
            'verified_by' => new UserResource($this->whenLoaded('verifiedBy')),
            'verified_at' => $this->verified_at,
            'version' => $this->version,
            'parent_document_id' => $this->parent_document_id,
            'download_url' => URL::temporarySignedRoute(
                'employee-documents.download',
                now()->addMinutes(15),
                ['employeeDocument' => $this->id],
            ),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Verification/rejection is a human decision, but "expiring soon"/"expired"
     * is purely a function of today's date vs expiry_date — computed on read
     * so it's never stale, rather than requiring a scheduled job to keep a
     * stored status column in sync.
     */
    private function computedStatus(): string
    {
        $status = $this->status?->value ?? (string) $this->status;

        if (in_array($status, ['pending_verification', 'rejected'], true)) {
            return $status;
        }

        if (! $this->expiry_date) {
            return 'verified' === $status ? 'valid' : $status;
        }

        if ($this->expiry_date->isPast()) {
            return 'expired';
        }

        if ($this->expiry_date->diffInDays(now(), absolute: true) <= 30) {
            return 'expiring_soon';
        }

        return 'verified' === $status ? 'valid' : $status;
    }
}
