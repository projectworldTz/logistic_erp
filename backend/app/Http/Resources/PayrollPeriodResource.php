<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'payment_date' => $this->payment_date,
            'pay_frequency' => $this->pay_frequency,
            'is_locked' => $this->is_locked,
            'latest_run' => $this->relationLoaded('runs') && $this->runs->isNotEmpty()
                ? new PayrollRunResource($this->runs->sortByDesc('run_number')->first())
                : null,
            'created_at' => $this->created_at,
        ];
    }
}
