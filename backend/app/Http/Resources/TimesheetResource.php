<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimesheetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'date' => $this->date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'total_hours' => $this->total_hours,
            'overtime_hours' => $this->overtime_hours,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'shipment_id' => $this->shipment_id,
            'clearing_file_id' => $this->clearing_file_id,
            'freight_booking_id' => $this->freight_booking_id,
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'activity' => $this->activity,
            'notes' => $this->notes,
            'status' => $this->status,
            'approved_by' => new UserResource($this->whenLoaded('approver')),
            'created_at' => $this->created_at,
        ];
    }
}
