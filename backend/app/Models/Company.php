<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Company extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'registration_number',
        'tax_number',
        'country',
        'city',
        'address',
        'currency',
        'timezone',
        'industry',
        'logo_path',
        'primary_color',
        'secondary_color',
        'email_footer_text',
        'email_reply_to',
        'notify_email_enabled',
        'notify_sms_enabled',
        'notify_whatsapp_enabled',
        'phone',
        'email',
        'website',
    ];

    protected $casts = [
        'notify_email_enabled' => 'boolean',
        'notify_sms_enabled' => 'boolean',
        'notify_whatsapp_enabled' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
