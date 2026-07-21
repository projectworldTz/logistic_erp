<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatutoryContributionRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'statutory_rule_set_id' => $this->statutory_rule_set_id,
            'code' => $this->code,
            'name' => $this->name,
            'employee_rate' => $this->employee_rate,
            'employer_rate' => $this->employer_rate,
            'min_base' => $this->min_base,
            'max_base' => $this->max_base,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
