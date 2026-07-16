<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ComplianceDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'issue_date' => $this->issue_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'status' => $this->complianceStatus(),
            'file_url' => $this->file_path ? Storage::disk('public')->url($this->file_path) : null,
            'notes' => $this->notes,
            'uploaded_by' => $this->uploaded_by,
            'uploaded_by_user' => new UserResource($this->whenLoaded('uploadedBy')),
            'created_at' => $this->created_at,
        ];
    }
}
