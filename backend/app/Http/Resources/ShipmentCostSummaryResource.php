<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentCostSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currency' => $this->resource['currency'],
            'revenue' => $this->resource['revenue'],
            'cost' => $this->resource['cost'],
            'profit' => $this->resource['profit'],
            'margin_percent' => $this->resource['margin_percent'],
            'cost_breakdown' => $this->resource['cost_breakdown'],
            'invoices' => InvoiceResource::collection($this->resource['invoices']),
            'expenses' => ExpenseResource::collection($this->resource['expenses']),
        ];
    }
}
