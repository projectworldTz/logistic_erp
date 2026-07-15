<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'type' => $this->type,
            'log_date' => $this->log_date,
            'description' => $this->description,
            'cost' => $this->cost,
            'currency' => $this->currency,
            'odometer_km' => $this->odometer_km,
            'liters' => $this->liters,
            'policy_number' => $this->policy_number,
            'expiry_date' => $this->expiry_date,
            'driver_id' => $this->driver_id,
            'driver' => new UserResource($this->whenLoaded('driver')),
            'origin' => $this->origin,
            'destination' => $this->destination,
            'distance_km' => $this->distance_km,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at,
        ];
    }
}
