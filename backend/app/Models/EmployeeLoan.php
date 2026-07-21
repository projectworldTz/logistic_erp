<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class EmployeeLoan extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'loan_number',
        'principal_amount',
        'interest_rate',
        'number_of_installments',
        'installment_amount',
        'start_date',
        'status',
        'reason',
        'approved_by',
        'disbursed_at',
        'created_by',
    ];

    protected $casts = [
        'status' => LoanStatus::class,
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:3',
        'installment_amount' => 'decimal:2',
        'start_date' => 'date',
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
        return $this->hasMany(LoanSchedule::class)->orderBy('installment_number');
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
