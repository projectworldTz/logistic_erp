<?php

namespace App\Models;

use App\Enums\PayrollDeductionType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollDeduction extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'payroll_run_employee_id',
        'payroll_component_id',
        'statutory_contribution_rule_id',
        'loan_schedule_id',
        'salary_advance_schedule_id',
        'type',
        'label',
        'amount',
    ];

    protected $casts = [
        'type' => PayrollDeductionType::class,
        'amount' => 'decimal:2',
    ];

    public function payrollRunEmployee(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class);
    }

    public function payrollComponent(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class);
    }

    public function contributionRule(): BelongsTo
    {
        return $this->belongsTo(StatutoryContributionRule::class, 'statutory_contribution_rule_id');
    }

    public function loanSchedule(): BelongsTo
    {
        return $this->belongsTo(LoanSchedule::class);
    }

    public function salaryAdvanceSchedule(): BelongsTo
    {
        return $this->belongsTo(SalaryAdvanceSchedule::class);
    }
}
