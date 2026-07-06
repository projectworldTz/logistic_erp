<?php

namespace App\Models;

use App\Enums\TrackingEventType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEvent extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'trackable_type',
        'trackable_id',
        'event_type',
        'location',
        'occurred_at',
        'notes',
        'is_customer_visible',
        'recorded_by',
    ];

    protected $casts = [
        'event_type' => TrackingEventType::class,
        'occurred_at' => 'datetime',
        'is_customer_visible' => 'boolean',
    ];

    public function trackable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
