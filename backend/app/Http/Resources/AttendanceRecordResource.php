<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'shift_id' => $this->shift_id,
            'shift' => new ShiftResource($this->whenLoaded('shift')),
            'date' => $this->date,
            'status' => $this->status,
            'source' => $this->source,
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            'late_minutes' => $this->late_minutes,
            'early_departure_minutes' => $this->early_departure_minutes,
            'is_weekend' => $this->is_weekend,
            'is_holiday' => $this->is_holiday,
            'approved_by' => new UserResource($this->whenLoaded('approver')),
            'approved_at' => $this->approved_at,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
