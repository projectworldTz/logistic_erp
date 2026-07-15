<?php

namespace App\Models;

use App\Enums\DetentionChargeStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetentionCharge extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'container_id',
        'customer_id',
        'detention_rate_card_id',
        'invoice_id',
        'calculated_at',
        'detention_days',
        'free_days',
        'chargeable_days',
        'amount',
        'currency',
        'breakdown',
        'status',
        'waived_reason',
    ];

    protected $casts = [
        'calculated_at' => 'datetime',
        'detention_days' => 'integer',
        'free_days' => 'integer',
        'chargeable_days' => 'integer',
        'amount' => 'decimal:2',
        'breakdown' => 'array',
        'status' => DetentionChargeStatus::class,
    ];

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(DetentionRateCard::class, 'detention_rate_card_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
