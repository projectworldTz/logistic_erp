<?php

namespace App\Models;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'shift_id',
        'date',
        'status',
        'source',
        'check_in',
        'check_out',
        'late_minutes',
        'early_departure_minutes',
        'is_weekend',
        'is_holiday',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'status' => AttendanceStatus::class,
        'source' => AttendanceSource::class,
        'date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'is_weekend' => 'boolean',
        'is_holiday' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
