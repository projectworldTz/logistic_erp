<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'leave_type_id',
        'year',
        'entitled_days',
        'used_days',
        'carried_forward_days',
    ];

    protected $casts = [
        'entitled_days' => 'decimal:1',
        'used_days' => 'decimal:1',
        'carried_forward_days' => 'decimal:1',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function getAvailableDaysAttribute(): float
    {
        return (float) $this->entitled_days + (float) $this->carried_forward_days - (float) $this->used_days;
    }
}
