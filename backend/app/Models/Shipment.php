<?php

namespace App\Models;

use App\Enums\ShipmentDirection;
use App\Enums\ShipmentStatus;
use App\Enums\TransportMode;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Shipment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'quotation_id',
        'clearing_file_id',
        'freight_booking_id',
        'shipment_number',
        'tracking_code',
        'direction',
        'mode',
        'origin_port',
        'destination_port',
        'bl_awb_number',
        'status',
        'etd',
        'eta',
        'notes',
    ];

    protected $casts = [
        'direction' => ShipmentDirection::class,
        'mode' => TransportMode::class,
        'status' => ShipmentStatus::class,
        'etd' => 'date',
        'eta' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function clearingFile(): BelongsTo
    {
        return $this->belongsTo(ClearingFile::class);
    }

    public function freightBooking(): BelongsTo
    {
        return $this->belongsTo(FreightBooking::class);
    }

    public function milestones(): MorphMany
    {
        return $this->morphMany(TrackingEvent::class, 'trackable')->orderBy('occurred_at');
    }
}
