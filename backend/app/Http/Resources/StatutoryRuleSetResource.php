<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatutoryRuleSetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country_code' => $this->country_code,
            'description' => $this->description,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'tax_bands' => StatutoryTaxBandResource::collection($this->whenLoaded('taxBands')),
            'contribution_rules' => StatutoryContributionRuleResource::collection($this->whenLoaded('contributionRules')),
            'created_at' => $this->created_at,
        ];
    }
}
