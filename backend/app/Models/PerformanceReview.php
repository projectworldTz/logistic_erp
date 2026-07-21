<?php

namespace App\Models;

use App\Enums\PerformanceReviewStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReview extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'reviewer_id',
        'review_period_start',
        'review_period_end',
        'review_date',
        'overall_rating',
        'kpi_scores',
        'strengths',
        'areas_for_improvement',
        'goals',
        'comments',
        'employee_comments',
        'status',
        'acknowledged_at',
        'created_by',
    ];

    protected $casts = [
        'status' => PerformanceReviewStatus::class,
        'review_period_start' => 'date',
        'review_period_end' => 'date',
        'review_date' => 'date',
        'overall_rating' => 'decimal:1',
        'kpi_scores' => 'array',
        'acknowledged_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
