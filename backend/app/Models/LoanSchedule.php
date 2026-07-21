<?php

namespace App\Models;

use App\Enums\LoanScheduleStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanSchedule extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_loan_id',
        'installment_number',
        'due_date',
        'amount',
        'status',
        'paid_in_payroll_run_id',
    ];

    protected $casts = [
        'status' => LoanScheduleStatus::class,
        'due_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }

    public function paidInPayrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'paid_in_payroll_run_id');
    }
}
