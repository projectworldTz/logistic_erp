<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetentionRateTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'from_day' => $this->from_day,
            'to_day' => $this->to_day,
            'daily_rate' => $this->daily_rate,
        ];
    }
}
