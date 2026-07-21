<?php

namespace App\Models;

use App\Enums\PayrollCalculationMethod;
use App\Enums\PayrollComponentType;
use App\Enums\PayrollPercentageBase;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollComponent extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'calculation_method',
        'amount',
        'percentage',
        'percentage_base',
        'formula_notes',
        'is_taxable',
        'is_pensionable',
        'is_recurring',
        'branch_id',
        'department_id',
        'designation_category',
        'effective_date',
        'end_date',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'type' => PayrollComponentType::class,
        'calculation_method' => PayrollCalculationMethod::class,
        'percentage_base' => PayrollPercentageBase::class,
        'amount' => 'decimal:2',
        'percentage' => 'decimal:3',
        'is_taxable' => 'boolean',
        'is_pensionable' => 'boolean',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
        'effective_date' => 'date',
        'end_date' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeePayrollComponent::class);
    }
}
