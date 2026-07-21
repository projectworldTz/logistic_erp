<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobVacancyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'designation_id' => $this->designation_id,
            'designation' => new DesignationResource($this->whenLoaded('designation')),
            'branch_id' => $this->branch_id,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'employment_type' => $this->employment_type,
            'number_of_openings' => $this->number_of_openings,
            'status' => $this->status,
            'posted_date' => $this->posted_date,
            'closing_date' => $this->closing_date,
            'applications_count' => $this->whenCounted('applications'),
            'created_at' => $this->created_at,
        ];
    }
}
