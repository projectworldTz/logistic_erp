<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'reviewer_id' => $this->reviewer_id,
            'reviewer' => new UserResource($this->whenLoaded('reviewer')),
            'review_period_start' => $this->review_period_start,
            'review_period_end' => $this->review_period_end,
            'review_date' => $this->review_date,
            'overall_rating' => $this->overall_rating,
            'kpi_scores' => $this->kpi_scores,
            'strengths' => $this->strengths,
            'areas_for_improvement' => $this->areas_for_improvement,
            'goals' => $this->goals,
            'comments' => $this->comments,
            'employee_comments' => $this->employee_comments,
            'status' => $this->status,
            'acknowledged_at' => $this->acknowledged_at,
            'created_at' => $this->created_at,
        ];
    }
}
