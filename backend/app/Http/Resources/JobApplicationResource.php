<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_vacancy_id' => $this->job_vacancy_id,
            'vacancy' => new JobVacancyResource($this->whenLoaded('vacancy')),
            'candidate_id' => $this->candidate_id,
            'candidate' => new CandidateResource($this->whenLoaded('candidate')),
            'applied_date' => $this->applied_date,
            'status' => $this->status,
            'notes' => $this->notes,
            'converted_employee_id' => $this->converted_employee_id,
            'interviews' => InterviewResource::collection($this->whenLoaded('interviews')),
            'created_at' => $this->created_at,
        ];
    }
}
