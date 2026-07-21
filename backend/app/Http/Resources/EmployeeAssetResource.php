<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'asset_type' => $this->asset_type,
            'asset_name' => $this->asset_name,
            'serial_number' => $this->serial_number,
            'assigned_date' => $this->assigned_date,
            'return_date' => $this->return_date,
            'condition_at_assignment' => $this->condition_at_assignment,
            'condition_at_return' => $this->condition_at_return,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
