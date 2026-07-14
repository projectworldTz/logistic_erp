<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentStep = $this->currentStep();

        return [
            'id' => $this->id,
            'workflow_id' => $this->approval_workflow_id,
            'workflow_name' => $this->whenLoaded('workflow', fn () => $this->workflow?->name),
            'status' => $this->status,
            'current_step_position' => $this->current_step_position,
            'total_steps' => $this->whenLoaded('workflow', fn () => $this->workflow?->steps->count()),
            'current_step_role' => $this->whenLoaded('workflow', fn () => $currentStep?->approver_role),
            'decisions' => ApprovalDecisionResource::collection($this->whenLoaded('decisions')),
        ];
    }
}
