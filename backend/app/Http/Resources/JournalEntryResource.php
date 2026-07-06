<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_number' => $this->entry_number,
            'entry_date' => $this->entry_date,
            'description' => $this->description,
            'reference' => $this->reference,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'created_by_user' => new UserResource($this->whenLoaded('createdBy')),
            'posted_at' => $this->posted_at,
            'lines' => JournalEntryLineResource::collection($this->whenLoaded('lines')),
            'total_debit' => $this->whenLoaded('lines', fn () => $this->lines->sum('debit')),
            'total_credit' => $this->whenLoaded('lines', fn () => $this->lines->sum('credit')),
            'created_at' => $this->created_at,
        ];
    }
}
