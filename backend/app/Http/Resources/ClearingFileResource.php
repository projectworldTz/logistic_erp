<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClearingFileResource extends JsonResource
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
            'port_of_loading' => $this->port_of_loading,
            'port_of_discharge' => $this->port_of_discharge,
            'bl_awb_number' => $this->bl_awb_number,
            'customs_office' => $this->customs_office,
            'declaration_number' => $this->declaration_number,
            'hs_code' => $this->hs_code,
            'cargo_description' => $this->cargo_description,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'assigned_to_user' => new UserResource($this->whenLoaded('assignedTo')),
            'duty_amount' => $this->duty_amount,
            'vat_amount' => $this->vat_amount,
            'other_charges' => $this->other_charges,
            'eta' => $this->eta,
            'cleared_date' => $this->cleared_date,
            'delivered_date' => $this->delivered_date,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
