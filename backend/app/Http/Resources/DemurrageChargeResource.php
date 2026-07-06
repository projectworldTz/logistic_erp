<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DemurrageChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'container_id' => $this->container_id,
            'container' => new ContainerResource($this->whenLoaded('container')),
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'invoice_id' => $this->invoice_id,
            'calculated_at' => $this->calculated_at,
            'dwell_days' => $this->dwell_days,
            'free_days' => $this->free_days,
            'chargeable_days' => $this->chargeable_days,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'breakdown' => $this->breakdown,
            'status' => $this->status,
            'waived_reason' => $this->waived_reason,
            'created_at' => $this->created_at,
        ];
    }
}
