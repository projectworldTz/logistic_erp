<?php

namespace App\Models;

use App\Enums\AssetType;
use App\Enums\EmployeeAssetStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAsset extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'asset_type',
        'asset_name',
        'serial_number',
        'assigned_date',
        'return_date',
        'condition_at_assignment',
        'condition_at_return',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'asset_type' => AssetType::class,
        'status' => EmployeeAssetStatus::class,
        'assigned_date' => 'date',
        'return_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
