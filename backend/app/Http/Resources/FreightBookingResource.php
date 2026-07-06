<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FreightBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'reference_no' => $this->reference_no,
            'direction' => $this->direction,
            'mode' => $this->mode,
            'carrier' => $this->carrier,
            'vessel_flight_no' => $this->vessel_flight_no,
            'booking_number' => $this->booking_number,
            'origin_port' => $this->origin_port,
            'destination_port' => $this->destination_port,
            'cargo_description' => $this->cargo_description,
            'weight_kg' => $this->weight_kg,
            'volume_cbm' => $this->volume_cbm,
            'freight_charges' => $this->freight_charges,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'assigned_to_user' => new UserResource($this->whenLoaded('assignedTo')),
            'etd' => $this->etd,
            'eta' => $this->eta,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
