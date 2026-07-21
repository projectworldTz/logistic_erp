<?php

namespace App\Models;

use App\Enums\PayrollRunEmployeeStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRunEmployee extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'payroll_run_id',
        'employee_id',
        'basic_salary',
        'gross_pay',
        'total_deductions',
        'total_employer_contributions',
        'net_pay',
        'status',
        'exception_notes',
    ];

    protected $casts = [
        'status' => PayrollRunEmployeeStatus::class,
        'basic_salary' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_employer_contributions' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(PayrollEarning::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayrollDeduction::class);
    }

    public function employerContributions(): HasMany
    {
        return $this->hasMany(PayrollEmployerContribution::class);
    }
}
