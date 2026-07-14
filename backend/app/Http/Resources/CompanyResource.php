<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'registration_number' => $this->registration_number,
            'tax_number' => $this->tax_number,
            'country' => $this->country,
            'city' => $this->city,
            'address' => $this->address,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'industry' => $this->industry,
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'notify_email_enabled' => $this->notify_email_enabled,
            'notify_sms_enabled' => $this->notify_sms_enabled,
            'notify_whatsapp_enabled' => $this->notify_whatsapp_enabled,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
        ];
    }
}
