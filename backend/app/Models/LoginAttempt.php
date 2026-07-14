<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginAttempt extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'successful',
        'reason',
    ];

    protected $casts = [
        'successful' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
