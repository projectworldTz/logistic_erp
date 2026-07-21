<?php

namespace App\Models;

use App\Enums\SalaryAdvanceStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class SalaryAdvance extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'advance_number',
        'amount',
        'number_of_installments',
        'installment_amount',
        'request_date',
        'status',
        'reason',
        'approved_by',
        'disbursed_at',
        'created_by',
    ];

    protected $casts = [
        'status' => SalaryAdvanceStatus::class,
        'amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'request_date' => 'date',
        'disbursed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SalaryAdvanceSchedule::class)->orderBy('installment_number');
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'subject');
    }

    public function latestApprovalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'subject')->latestOfMany();
    }
}
