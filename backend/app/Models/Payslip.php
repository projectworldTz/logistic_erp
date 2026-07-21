<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'payroll_run_employee_id',
        'employee_id',
        'payroll_run_id',
        'payslip_number',
        'gross_pay',
        'total_deductions',
        'net_pay',
        'total_employer_contributions',
        'ytd_gross',
        'ytd_deductions',
        'ytd_net',
        'verification_code',
    ];

    protected $casts = [
        'gross_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'total_employer_contributions' => 'decimal:2',
        'ytd_gross' => 'decimal:2',
        'ytd_deductions' => 'decimal:2',
        'ytd_net' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function payrollRunEmployee(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class);
    }
}
