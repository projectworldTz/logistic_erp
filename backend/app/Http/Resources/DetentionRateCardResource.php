<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetentionRateCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'container_type' => $this->container_type,
            'free_days' => $this->free_days,
            'currency' => $this->currency,
            'is_default' => $this->is_default,
            'tiers' => DetentionRateTierResource::collection($this->whenLoaded('tiers')),
            'created_at' => $this->created_at,
        ];
    }
}
