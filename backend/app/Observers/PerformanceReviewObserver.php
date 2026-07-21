<?php

namespace App\Observers;

use App\Models\PerformanceReview;
use App\Models\UserNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;

class PerformanceReviewObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(PerformanceReview $review): void
    {
        $this->auditLogger->log(
            action: 'performance_review.created',
            auditable: $review,
            newValues: $review->only(['employee_id', 'review_period_start', 'review_period_end', 'overall_rating']),
            tenantId: $review->tenant_id,
        );
    }

    public function updated(PerformanceReview $review): void
    {
        if (! $review->wasChanged('status')) {
            return;
        }

        $this->auditLogger->log(
            action: 'performance_review.status_changed',
            auditable: $review,
            oldValues: ['status' => $review->getOriginal('status')],
            newValues: ['status' => $review->status->value],
            tenantId: $review->tenant_id,
        );

        if ($review->status->value === 'submitted' && $review->employee?->user_id) {
            $now = now();

            UserNotification::query()->insert([[
                'tenant_id' => $review->tenant_id,
                'user_id' => $review->employee->user_id,
                'actor_id' => Auth::id(),
                'type' => 'performance_review.submitted',
                'notifiable_type' => $review->getMorphClass(),
                'notifiable_id' => $review->getKey(),
                'title' => 'New performance review',
                'message' => 'A performance review has been submitted and needs your acknowledgement.',
                'created_at' => $now,
                'updated_at' => $now,
            ]]);
        }
    }
}
