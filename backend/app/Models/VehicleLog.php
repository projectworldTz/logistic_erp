<?php

namespace App\Models;

use App\Enums\VehicleLogType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleLog extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'type',
        'log_date',
        'description',
        'cost',
        'currency',
        'odometer_km',
        'liters',
        'policy_number',
        'expiry_date',
        'driver_id',
        'origin',
        'destination',
        'distance_km',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'type' => VehicleLogType::class,
        'log_date' => 'date',
        'cost' => 'decimal:2',
        'odometer_km' => 'decimal:2',
        'liters' => 'decimal:2',
        'expiry_date' => 'date',
        'distance_km' => 'decimal:2',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
