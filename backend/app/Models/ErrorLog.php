<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorLog extends Model
{
    protected $fillable = [
        'reference',
        'tenant_id',
        'user_id',
        'exception_class',
        'message',
        'file',
        'line',
        'trace',
        'method',
        'url',
        'status_code',
        'request_payload',
        'ip_address',
        'user_agent',
        'resolved_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
