<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledReport extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'created_by',
        'name',
        'module',
        'format',
        'frequency',
        'recipients',
        'is_active',
        'last_sent_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->last_sent_at) {
            return true;
        }

        return match ($this->frequency) {
            'daily' => $this->last_sent_at->lt(now()->subDay()),
            'weekly' => $this->last_sent_at->lt(now()->subWeek()),
            'monthly' => $this->last_sent_at->lt(now()->subMonth()),
            default => false,
        };
    }
}
