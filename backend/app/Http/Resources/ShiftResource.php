<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'break_minutes' => $this->break_minutes,
            'grace_minutes' => $this->grace_minutes,
            'overtime_threshold_hours' => $this->overtime_threshold_hours,
            'night_allowance_amount' => $this->night_allowance_amount,
            'weekend_rules' => $this->weekend_rules,
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
