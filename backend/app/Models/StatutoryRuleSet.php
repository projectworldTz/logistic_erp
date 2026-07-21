<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatutoryRuleSet extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'country_code',
        'description',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function taxBands(): HasMany
    {
        return $this->hasMany(StatutoryTaxBand::class)->orderBy('band_order');
    }

    public function contributionRules(): HasMany
    {
        return $this->hasMany(StatutoryContributionRule::class)->orderBy('sort_order');
    }
}
