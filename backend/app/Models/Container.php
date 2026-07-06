<?php

namespace App\Models;

use App\Enums\ContainerStatus;
use App\Enums\ContainerType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Container extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'clearing_file_id',
        'freight_booking_id',
        'container_number',
        'container_type',
        'seal_number',
        'status',
        'gross_weight_kg',
        'location',
        'gate_in_date',
        'gate_out_date',
        'notes',
    ];

    protected $casts = [
        'container_type' => ContainerType::class,
        'status' => ContainerStatus::class,
        'gross_weight_kg' => 'decimal:2',
        'gate_in_date' => 'date',
        'gate_out_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function clearingFile(): BelongsTo
    {
        return $this->belongsTo(ClearingFile::class);
    }

    public function freightBooking(): BelongsTo
    {
        return $this->belongsTo(FreightBooking::class);
    }

    public function demurrageCharges(): HasMany
    {
        return $this->hasMany(DemurrageCharge::class);
    }
}
