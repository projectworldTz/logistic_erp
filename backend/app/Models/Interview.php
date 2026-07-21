<?php

namespace App\Models;

use App\Enums\InterviewMode;
use App\Enums\InterviewStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interview extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'job_application_id',
        'interviewer_id',
        'scheduled_at',
        'mode',
        'location',
        'status',
        'feedback',
        'rating',
        'created_by',
    ];

    protected $casts = [
        'mode' => InterviewMode::class,
        'status' => InterviewStatus::class,
        'scheduled_at' => 'datetime',
        'rating' => 'decimal:1',
    ];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }
}
