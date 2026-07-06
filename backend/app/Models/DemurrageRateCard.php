<?php

namespace App\Models;

use App\Enums\ContainerType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemurrageRateCard extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'container_type',
        'free_days',
        'currency',
        'is_default',
    ];

    protected $casts = [
        'container_type' => ContainerType::class,
        'free_days' => 'integer',
        'is_default' => 'boolean',
    ];

    public function tiers(): HasMany
    {
        return $this->hasMany(DemurrageRateTier::class)->orderBy('position');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(DemurrageCharge::class);
    }
}
