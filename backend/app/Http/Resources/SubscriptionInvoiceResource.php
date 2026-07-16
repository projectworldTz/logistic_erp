<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_name' => $this->plan_name,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
        ];
    }
}
