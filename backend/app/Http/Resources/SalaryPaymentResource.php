<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'mobile_money_provider' => $this->mobile_money_provider,
            'mobile_money_number' => $this->mobile_money_number,
            'status' => $this->status,
            'reference' => $this->reference,
            'paid_at' => $this->paid_at,
        ];
    }
}
