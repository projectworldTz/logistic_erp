<?php

namespace App\Models;

use App\Enums\WarehouseItemStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'branch_id',
        'reference_no',
        'description',
        'quantity',
        'unit',
        'bin_location',
        'weight_kg',
        'volume_cbm',
        'status',
        'received_date',
        'dispatched_date',
        'notes',
    ];

    protected $casts = [
        'status' => WarehouseItemStatus::class,
        'quantity' => 'decimal:2',
        'weight_kg' => 'decimal:2',
        'volume_cbm' => 'decimal:2',
        'received_date' => 'date',
        'dispatched_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
