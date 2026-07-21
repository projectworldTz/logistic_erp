<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatutoryTaxBand extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'statutory_rule_set_id',
        'lower_bound',
        'upper_bound',
        'rate',
        'band_order',
    ];

    protected $casts = [
        'lower_bound' => 'decimal:2',
        'upper_bound' => 'decimal:2',
        'rate' => 'decimal:3',
    ];

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(StatutoryRuleSet::class, 'statutory_rule_set_id');
    }
}
