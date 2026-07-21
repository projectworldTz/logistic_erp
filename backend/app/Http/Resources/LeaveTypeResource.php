<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_paid' => $this->is_paid,
            'accrual_rule' => $this->accrual_rule,
            'default_annual_days' => $this->default_annual_days,
            'carry_forward_max_days' => $this->carry_forward_max_days,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
