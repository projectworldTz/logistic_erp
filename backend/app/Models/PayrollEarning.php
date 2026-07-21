<?php

namespace App\Models;

use App\Enums\PayrollEarningSource;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEarning extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'payroll_run_employee_id',
        'payroll_component_id',
        'source',
        'label',
        'amount',
        'is_taxable',
        'is_pensionable',
    ];

    protected $casts = [
        'source' => PayrollEarningSource::class,
        'amount' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_pensionable' => 'boolean',
    ];

    public function payrollRunEmployee(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class);
    }

    public function payrollComponent(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class);
    }
}
