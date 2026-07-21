<?php

namespace App\Models;

use App\Enums\SalaryPaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPayment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'salary_payment_batch_id',
        'payroll_run_employee_id',
        'employee_id',
        'amount',
        'payment_method',
        'bank_name',
        'bank_account_number',
        'mobile_money_provider',
        'mobile_money_number',
        'status',
        'reference',
        'paid_at',
    ];

    protected $casts = [
        'status' => SalaryPaymentStatus::class,
        'amount' => 'decimal:2',
        'bank_account_number' => 'encrypted',
        'mobile_money_number' => 'encrypted',
        'paid_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SalaryPaymentBatch::class, 'salary_payment_batch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollRunEmployee(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class);
    }
}
