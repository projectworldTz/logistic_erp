<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemurrageRateTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'demurrage_rate_card_id',
        'position',
        'from_day',
        'to_day',
        'daily_rate',
    ];

    protected $casts = [
        'position' => 'integer',
        'from_day' => 'integer',
        'to_day' => 'integer',
        'daily_rate' => 'decimal:2',
    ];

    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(DemurrageRateCard::class, 'demurrage_rate_card_id');
    }
}
