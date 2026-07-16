<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'billing_name' => $this->billing_name,
            'billing_email' => $this->billing_email,
            'billing_phone' => $this->billing_phone,
            'billing_address' => $this->billing_address,
            'tax_id' => $this->tax_id,
            'payment_method_type' => $this->payment_method_type,
            'payment_reference' => $this->payment_reference,
        ];
    }
}
