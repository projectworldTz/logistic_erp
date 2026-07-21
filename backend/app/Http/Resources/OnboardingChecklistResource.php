<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingChecklistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'status' => $this->status,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'tasks' => OnboardingTaskResource::collection($this->whenLoaded('tasks')),
            'progress' => $this->when(
                $this->relationLoaded('tasks') && $this->tasks->isNotEmpty(),
                fn () => round(($this->tasks->where('is_completed', true)->count() / $this->tasks->count()) * 100),
            ),
            'created_at' => $this->created_at,
        ];
    }
}
