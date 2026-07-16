<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduledReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'module' => $this->module,
            'format' => $this->format,
            'frequency' => $this->frequency,
            'recipients' => $this->recipients,
            'is_active' => $this->is_active,
            'last_sent_at' => $this->last_sent_at,
            'created_at' => $this->created_at,
        ];
    }
}
