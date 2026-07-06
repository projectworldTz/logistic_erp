<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'is_primary' => $this->is_primary,
        ];
    }
}
