<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'billing_cycle',
        'starts_at',
        'trial_ends_at',
        'ends_at',
        'canceled_at',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'starts_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }
}
