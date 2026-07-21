<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\PreferredPaymentMethod;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'department_id',
        'branch_id',
        'user_id',
        'employee_number',
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'phone',
        'alternative_phone',
        'job_title',
        'employment_type',
        'status',
        'hire_date',
        'termination_date',
        'salary',
        'notes',
        // Personal information
        'gender',
        'date_of_birth',
        'nationality',
        'marital_status',
        'photo_path',
        'residential_address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'national_id_number',
        // Employment information
        'designation_id',
        'employee_category',
        'confirmation_date',
        'probation_end_date',
        'work_location',
        'reporting_manager_id',
        'payroll_eligible',
        'notice_period_days',
        // Bank and payment information
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'bank_branch_name',
        'mobile_money_provider',
        'mobile_money_number',
        'preferred_payment_method',
        'pay_currency',
        // Configurable statutory identifiers
        'statutory_details',
    ];

    protected $casts = [
        'employment_type' => EmploymentType::class,
        'status' => EmployeeStatus::class,
        'preferred_payment_method' => PreferredPaymentMethod::class,
        'hire_date' => 'date',
        'termination_date' => 'date',
        'confirmation_date' => 'date',
        'probation_end_date' => 'date',
        'date_of_birth' => 'date',
        'salary' => 'decimal:2',
        'payroll_eligible' => 'boolean',
        'statutory_details' => 'array',
        'national_id_number' => 'encrypted',
        'bank_account_number' => 'encrypted',
        'mobile_money_number' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::saving(function (Employee $employee) {
            if ($employee->first_name || $employee->last_name) {
                $employee->name = trim(implode(' ', array_filter([
                    $employee->first_name, $employee->middle_name, $employee->last_name,
                ])));
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'reporting_manager_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function activeContract(): HasMany
    {
        return $this->contracts()->where('status', 'active');
    }

    public function payrollComponents(): HasMany
    {
        return $this->hasMany(EmployeePayrollComponent::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function salaryAdvances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class);
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }

    public function disciplinaryRecords(): HasMany
    {
        return $this->hasMany(DisciplinaryRecord::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(EmployeeAsset::class);
    }

    public function exitRecord(): HasOne
    {
        return $this->hasOne(ExitRecord::class);
    }

    public function onboardingChecklist(): HasOne
    {
        return $this->hasOne(OnboardingChecklist::class);
    }
}
