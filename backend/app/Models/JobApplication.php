<?php

namespace App\Models;

use App\Enums\JobApplicationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobApplication extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'job_vacancy_id',
        'candidate_id',
        'applied_date',
        'status',
        'notes',
        'converted_employee_id',
        'created_by',
    ];

    protected $casts = [
        'status' => JobApplicationStatus::class,
        'applied_date' => 'date',
    ];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'job_vacancy_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function convertedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'converted_employee_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }
}
