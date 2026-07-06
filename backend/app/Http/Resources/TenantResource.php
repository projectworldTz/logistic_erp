<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'trial_ends_at' => $this->trial_ends_at,
            'suspended_at' => $this->suspended_at,
            'created_at' => $this->created_at,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
        ];
    }
}
