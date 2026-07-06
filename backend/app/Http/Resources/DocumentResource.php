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
            'category' => $this->category,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'url' => Storage::disk('public')->url($this->file_path),
            'uploaded_by' => $this->uploaded_by,
            'uploaded_by_user' => new UserResource($this->whenLoaded('uploadedBy')),
            'description' => $this->description,
            'created_at' => $this->created_at,
        ];
    }
}
