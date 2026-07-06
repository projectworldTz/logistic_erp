<?php

namespace App\Models;

use App\Enums\FreightDirection;
use App\Enums\FreightStatus;
use App\Enums\TransportMode;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreightBooking extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'reference_no',
        'direction',
        'mode',
        'carrier',
        'vessel_flight_no',
        'booking_number',
        'origin_port',
        'destination_port',
        'cargo_description',
        'weight_kg',
        'volume_cbm',
        'freight_charges',
        'status',
        'assigned_to',
        'etd',
        'eta',
        'notes',
    ];

    protected $casts = [
        'direction' => FreightDirection::class,
        'mode' => TransportMode::class,
        'status' => FreightStatus::class,
        'weight_kg' => 'decimal:2',
        'volume_cbm' => 'decimal:2',
        'freight_charges' => 'decimal:2',
        'etd' => 'date',
        'eta' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
