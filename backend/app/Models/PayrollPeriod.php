<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'period_start',
        'period_end',
        'payment_date',
        'pay_frequency',
        'is_locked',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'is_locked' => 'boolean',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    public function latestRun(): ?PayrollRun
    {
        return $this->runs()->orderByDesc('run_number')->first();
    }
}
