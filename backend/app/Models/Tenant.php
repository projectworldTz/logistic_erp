<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'timezone',
        'currency',
        'trial_ends_at',
        'suspended_at',
    ];

    protected $casts = [
        'status' => TenantStatus::class,
        'trial_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function billingProfile(): HasOne
    {
        return $this->hasOne(BillingProfile::class);
    }

    public function dashboardSetting(): HasOne
    {
        return $this->hasOne(TenantDashboardSetting::class);
    }
}
