<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'grace_minutes',
        'overtime_threshold_hours',
        'night_allowance_amount',
        'weekend_rules',
        'branch_id',
        'department_id',
        'is_active',
    ];

    protected $casts = [
        'overtime_threshold_hours' => 'decimal:2',
        'night_allowance_amount' => 'decimal:2',
        'weekend_rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employeeShifts(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }
}
