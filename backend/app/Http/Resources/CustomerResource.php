<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'industry' => $this->industry,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'currency' => $this->currency,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'assigned_to_user' => new UserResource($this->whenLoaded('assignedTo')),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'created_at' => $this->created_at,
        ];
    }
}
