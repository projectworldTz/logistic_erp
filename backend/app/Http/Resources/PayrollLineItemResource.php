<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollLineItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'amount' => $this->amount,
            'source' => $this->when(isset($this->source), $this->source),
            'type' => $this->when(isset($this->type), $this->type),
            'is_taxable' => $this->when(isset($this->is_taxable), $this->is_taxable),
            'is_pensionable' => $this->when(isset($this->is_pensionable), $this->is_pensionable),
            'payroll_component_id' => $this->payroll_component_id,
        ];
    }
}
