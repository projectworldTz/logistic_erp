<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProofOfDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'received_by_name' => $this->received_by_name,
            'signature_url' => Storage::disk('public')->url($this->signature_path),
            'photo_url' => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'notes' => $this->notes,
            'captured_by' => $this->captured_by,
            'captured_by_user' => new UserResource($this->whenLoaded('capturedBy')),
            'created_at' => $this->created_at,
        ];
    }
}
