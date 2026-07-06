<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'sender_user_id' => $this->sender_user_id,
            'sender_user' => new UserResource($this->whenLoaded('senderUser')),
            'is_from_customer' => $this->is_from_customer,
            'body' => $this->body,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
