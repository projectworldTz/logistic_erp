<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContainerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'clearing_file_id' => $this->clearing_file_id,
            'freight_booking_id' => $this->freight_booking_id,
            'container_number' => $this->container_number,
            'container_type' => $this->container_type,
            'shipping_line' => $this->shipping_line,
            'vessel_name' => $this->vessel_name,
            'voyage_number' => $this->voyage_number,
            'port_of_loading' => $this->port_of_loading,
            'port_of_discharge' => $this->port_of_discharge,
            'seal_number' => $this->seal_number,
            'status' => $this->status,
            'gross_weight_kg' => $this->gross_weight_kg,
            'location' => $this->location,
            'gate_in_date' => $this->gate_in_date,
            'eta' => $this->eta,
            'ata' => $this->ata,
            'gate_out_date' => $this->gate_out_date,
            'empty_return_date' => $this->empty_return_date,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
