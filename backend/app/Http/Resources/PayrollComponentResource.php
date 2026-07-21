<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'calculation_method' => $this->calculation_method,
            'amount' => $this->amount,
            'percentage' => $this->percentage,
            'percentage_base' => $this->percentage_base,
            'formula_notes' => $this->formula_notes,
            'is_taxable' => $this->is_taxable,
            'is_pensionable' => $this->is_pensionable,
            'is_recurring' => $this->is_recurring,
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'designation_category' => $this->designation_category,
            'effective_date' => $this->effective_date,
            'end_date' => $this->end_date,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
        ];
    }
}
