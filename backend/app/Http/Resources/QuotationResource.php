<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'quotation_number' => $this->quotation_number,
            'direction' => $this->direction,
            'mode' => $this->mode,
            'origin_port' => $this->origin_port,
            'destination_port' => $this->destination_port,
            'issue_date' => $this->issue_date,
            'valid_until' => $this->valid_until,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'items' => QuotationItemResource::collection($this->whenLoaded('items')),
            'has_shipment' => $this->shipments()->exists(),
            'approval_request' => $this->whenLoaded(
                'latestApprovalRequest',
                fn () => $this->latestApprovalRequest ? new ApprovalRequestResource($this->latestApprovalRequest) : null,
            ),
            'created_at' => $this->created_at,
        ];
    }
}
