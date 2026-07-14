<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_number' => $this->expense_number,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'shipment_id' => $this->shipment_id,
            'shipment' => new ShipmentResource($this->whenLoaded('shipment')),
            'clearing_file_id' => $this->clearing_file_id,
            'freight_booking_id' => $this->freight_booking_id,
            'category' => $this->category,
            'description' => $this->description,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'expense_date' => $this->expense_date,
            'is_billable' => $this->is_billable,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'approved_by' => $this->approved_by,
            'approver' => new UserResource($this->whenLoaded('approver')),
            'rejection_reason' => $this->rejection_reason,
            'paid_at' => $this->paid_at,
            'notes' => $this->notes,
            'approval_request' => $this->whenLoaded(
                'latestApprovalRequest',
                fn () => $this->latestApprovalRequest ? new ApprovalRequestResource($this->latestApprovalRequest) : null,
            ),
            'created_at' => $this->created_at,
        ];
    }
}
