<?php

namespace App\Models;

use App\Enums\PayrollRunStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class PayrollRun extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'payroll_period_id',
        'run_number',
        'status',
        'statutory_rule_set_id',
        'total_gross',
        'total_deductions',
        'total_net',
        'total_employer_contributions',
        'total_employer_cost',
        'calculated_at',
        'approved_at',
        'finalized_at',
        'created_by',
        'journal_entry_id',
        'posted_at',
        'notes',
    ];

    protected $casts = [
        'status' => PayrollRunStatus::class,
        'total_gross' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net' => 'decimal:2',
        'total_employer_contributions' => 'decimal:2',
        'total_employer_cost' => 'decimal:2',
        'calculated_at' => 'datetime',
        'approved_at' => 'datetime',
        'finalized_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function statutoryRuleSet(): BelongsTo
    {
        return $this->belongsTo(StatutoryRuleSet::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function runEmployees(): HasMany
    {
        return $this->hasMany(PayrollRunEmployee::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function salaryPaymentBatch(): HasOne
    {
        return $this->hasOne(SalaryPaymentBatch::class);
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
