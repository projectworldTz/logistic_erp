<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'location' => $this->location,
            'occurred_at' => $this->occurred_at,
            'notes' => $this->notes,
            'is_customer_visible' => $this->is_customer_visible,
            'recorded_by' => $this->recorded_by,
            'recorded_by_user' => new UserResource($this->whenLoaded('recordedBy')),
            'created_at' => $this->created_at,
        ];
    }
}
