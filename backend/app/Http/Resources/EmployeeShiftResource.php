<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'shift_id' => $this->shift_id,
            'shift' => new ShiftResource($this->whenLoaded('shift')),
            'effective_date' => $this->effective_date,
            'end_date' => $this->end_date,
            'created_at' => $this->created_at,
        ];
    }
}
