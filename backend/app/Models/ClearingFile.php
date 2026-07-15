<?php

namespace App\Models;

use App\Enums\ClearingDirection;
use App\Enums\ClearingStatus;
use App\Enums\CustomsAssessmentStatus;
use App\Enums\TransportMode;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClearingFile extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'reference_no',
        'direction',
        'mode',
        'port_of_loading',
        'port_of_discharge',
        'bl_awb_number',
        'customs_office',
        'declaration_number',
        'sad_number',
        'hs_code',
        'customs_value',
        'cargo_description',
        'status',
        'assigned_to',
        'duty_amount',
        'vat_amount',
        'other_charges',
        'eta',
        'cleared_date',
        'release_order_number',
        'assessment_status',
        'delivered_date',
        'notes',
    ];

    protected $casts = [
        'direction' => ClearingDirection::class,
        'mode' => TransportMode::class,
        'status' => ClearingStatus::class,
        'assessment_status' => CustomsAssessmentStatus::class,
        'duty_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'customs_value' => 'decimal:2',
        'eta' => 'date',
        'cleared_date' => 'date',
        'delivered_date' => 'date',
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
