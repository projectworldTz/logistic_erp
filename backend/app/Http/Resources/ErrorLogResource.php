<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ErrorLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'tenant' => $this->whenLoaded('tenant', fn () => $this->tenant ? [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
            ] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'exception_class' => $this->exception_class,
            'message' => $this->message,
            'status_code' => $this->status_code,
            'method' => $this->method,
            'url' => $this->url,
            'file' => $this->file,
            'line' => $this->line,
            'trace' => $this->trace,
            'request_payload' => $this->request_payload,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
        ];
    }
}
