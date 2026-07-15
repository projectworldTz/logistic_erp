<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'shipment_id' => $this->shipment_id,
            'shipment' => new ShipmentResource($this->whenLoaded('shipment')),
            'category' => $this->category,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'is_previewable' => str_starts_with((string) $this->mime_type, 'image/') || $this->mime_type === 'application/pdf',
            'url' => Storage::disk('public')->url($this->file_path),
            'version' => $this->version,
            'parent_document_id' => $this->parent_document_id,
            'root_document_id' => $this->root_document_id,
            'uploaded_by' => $this->uploaded_by,
            'uploaded_by_user' => new UserResource($this->whenLoaded('uploadedBy')),
            'description' => $this->description,
            'created_at' => $this->created_at,
        ];
    }
}
