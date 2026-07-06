<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'registration_number',
        'vehicle_type',
        'make',
        'model',
        'year',
        'capacity_kg',
        'status',
        'assigned_driver',
        'last_service_date',
        'next_service_due',
        'notes',
    ];

    protected $casts = [
        'vehicle_type' => VehicleType::class,
        'status' => VehicleStatus::class,
        'capacity_kg' => 'decimal:2',
        'last_service_date' => 'date',
        'next_service_due' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_driver');
    }
}
