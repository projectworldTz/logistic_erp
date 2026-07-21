<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatutoryTaxBandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'statutory_rule_set_id' => $this->statutory_rule_set_id,
            'lower_bound' => $this->lower_bound,
            'upper_bound' => $this->upper_bound,
            'rate' => $this->rate,
            'band_order' => $this->band_order,
        ];
    }
}
