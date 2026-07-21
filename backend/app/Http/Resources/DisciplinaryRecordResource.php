<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisciplinaryRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'incident_date' => $this->incident_date,
            'category' => $this->category,
            'severity' => $this->severity,
            'description' => $this->description,
            'action_taken' => $this->action_taken,
            'issued_by' => $this->issued_by,
            'issuedBy' => new UserResource($this->whenLoaded('issuedBy')),
            'status' => $this->status,
            'employee_response' => $this->employee_response,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
        ];
    }
}
