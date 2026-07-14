<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'quotation_id' => $this->quotation_id,
            'clearing_file_id' => $this->clearing_file_id,
            'freight_booking_id' => $this->freight_booking_id,
            'shipment_number' => $this->shipment_number,
            'tracking_code' => $this->tracking_code,
            'direction' => $this->direction,
            'mode' => $this->mode,
            'origin_port' => $this->origin_port,
            'destination_port' => $this->destination_port,
            'bl_awb_number' => $this->bl_awb_number,
            'status' => $this->status,
            'is_at_risk' => $this->eta !== null
                && $this->eta->isPast()
                && ! in_array($this->status?->value, ['arrived', 'delivered', 'cancelled'], true),
            'etd' => $this->etd,
            'eta' => $this->eta,
            'notes' => $this->notes,
            'milestones' => TrackingEventResource::collection($this->whenLoaded('milestones')),
            'created_at' => $this->created_at,
        ];
    }
}
