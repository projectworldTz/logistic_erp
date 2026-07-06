<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'reference_no' => $this->reference_no,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'bin_location' => $this->bin_location,
            'weight_kg' => $this->weight_kg,
            'volume_cbm' => $this->volume_cbm,
            'status' => $this->status,
            'received_date' => $this->received_date,
            'dispatched_date' => $this->dispatched_date,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
