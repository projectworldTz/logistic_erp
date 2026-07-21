<?php

namespace App\Models;

use App\Enums\SalaryPaymentBatchStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryPaymentBatch extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'payroll_run_id',
        'batch_number',
        'payment_date',
        'status',
        'total_amount',
        'created_by',
    ];

    protected $casts = [
        'status' => SalaryPaymentBatchStatus::class,
        'payment_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }
}
