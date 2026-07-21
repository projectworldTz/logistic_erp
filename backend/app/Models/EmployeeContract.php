<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Enums\EmploymentType;
use App\Enums\PayFrequency;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class EmployeeContract extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'contract_number',
        'employment_type',
        'effective_date',
        'expiry_date',
        'basic_salary',
        'pay_frequency',
        'working_hours_per_week',
        'workdays',
        'probation_period_days',
        'notice_period_days',
        'benefits',
        'overtime_eligible',
        'commission_eligible',
        'leave_entitlement_days',
        'document_path',
        'status',
        'created_by',
        'approved_by',
        'renewed_from_contract_id',
        'notes',
    ];

    protected $casts = [
        'employment_type' => EmploymentType::class,
        'pay_frequency' => PayFrequency::class,
        'status' => ContractStatus::class,
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'basic_salary' => 'decimal:2',
        'workdays' => 'array',
        'overtime_eligible' => 'boolean',
        'commission_eligible' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(EmployeeContract::class, 'renewed_from_contract_id');
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
