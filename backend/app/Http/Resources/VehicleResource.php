<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'registration_number' => $this->registration_number,
            'vehicle_type' => $this->vehicle_type,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'capacity_kg' => $this->capacity_kg,
            'status' => $this->status,
            'assigned_driver' => $this->assigned_driver,
            'assigned_driver_user' => new UserResource($this->whenLoaded('assignedDriver')),
            'last_service_date' => $this->last_service_date,
            'next_service_due' => $this->next_service_due,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
