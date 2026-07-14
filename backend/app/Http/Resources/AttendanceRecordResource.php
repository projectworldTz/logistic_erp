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
            'date' => $this->date,
            'status' => $this->status,
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
