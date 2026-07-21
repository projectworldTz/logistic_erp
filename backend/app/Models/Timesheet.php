<?php

namespace App\Models;

use App\Enums\TimesheetStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timesheet extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'date',
        'start_time',
        'end_time',
        'total_hours',
        'overtime_hours',
        'customer_id',
        'shipment_id',
        'clearing_file_id',
        'freight_booking_id',
        'department_id',
        'activity',
        'notes',
        'status',
        'approved_by',
    ];

    protected $casts = [
        'status' => TimesheetStatus::class,
        'date' => 'date',
        'total_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function clearingFile(): BelongsTo
    {
        return $this->belongsTo(ClearingFile::class);
    }

    public function freightBooking(): BelongsTo
    {
        return $this->belongsTo(FreightBooking::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
