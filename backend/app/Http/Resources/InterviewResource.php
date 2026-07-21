<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_application_id' => $this->job_application_id,
            'interviewer_id' => $this->interviewer_id,
            'interviewer' => new UserResource($this->whenLoaded('interviewer')),
            'scheduled_at' => $this->scheduled_at,
            'mode' => $this->mode,
            'location' => $this->location,
            'status' => $this->status,
            'feedback' => $this->feedback,
            'rating' => $this->rating,
            'created_at' => $this->created_at,
        ];
    }
}
