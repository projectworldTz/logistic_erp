<?php

namespace App\Listeners\Identity;

use App\Events\Identity\EmployeeIdentityOverridden;
use App\Events\Identity\IdentityManualReviewApproved;
use App\Events\Identity\IdentityManualReviewRejected;
use App\Events\Identity\IdentityManualReviewSubmitted;
use App\Events\Identity\IdentityVerificationFailed;
use App\Events\Identity\IdentityVerificationSucceeded;
use App\Services\Notifications\NotificationService;
use Illuminate\Events\Dispatcher;

/**
 * In-app notifications for the identity module. Never includes a full or
 * even masked identity number in the title/message — only the employee
 * name (if already linked) or a generic reference.
 */
class IdentityNotificationSubscriber
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handleSucceeded(IdentityVerificationSucceeded $event): void
    {
        $verification = $event->verification;

        $this->notifications->notifyModuleUsers(
            'identity.view',
            'identity_verification.succeeded',
            'Identity verified successfully',
            'An employee identity check came back verified and is ready for confirmation.',
            $verification,
            $verification->requested_by,
        );
    }

    public function handleFailed(IdentityVerificationFailed $event): void
    {
        $verification = $event->verification;

        $this->notifications->notifyModuleUsers(
            'identity.view',
            'identity_verification.failed',
            'Identity verification failed',
            $event->reason,
            $verification,
            $verification->requested_by,
        );
    }

    public function handleManualReviewSubmitted(IdentityManualReviewSubmitted $event): void
    {
        $this->notifications->notifyModuleUsers(
            'identity.manual-review.approve',
            'identity_manual_review.submitted',
            'Identity requires manual review',
            'A manual identity review was submitted and is awaiting a decision.',
            $event->review,
            $event->review->submitted_by,
        );
    }

    public function handleManualReviewApproved(IdentityManualReviewApproved $event): void
    {
        $this->notifications->notifyModuleUsers(
            'identity.view',
            'identity_manual_review.approved',
            'Manual review approved',
            'A manual identity review was approved.',
            $event->review,
            $event->review->reviewed_by,
        );
    }

    public function handleManualReviewRejected(IdentityManualReviewRejected $event): void
    {
        $this->notifications->notifyModuleUsers(
            'identity.view',
            'identity_manual_review.rejected',
            'Manual review rejected',
            'A manual identity review was rejected.',
            $event->review,
            $event->review->reviewed_by,
        );
    }

    public function handleOverridden(EmployeeIdentityOverridden $event): void
    {
        $this->notifications->notifyModuleUsers(
            'identity.override',
            'identity_verification.overridden',
            'Employee identity information overridden',
            "{$event->employee->name}'s identity-sourced information was manually overridden.",
            $event->employee,
            $event->overriddenBy->id,
        );
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            IdentityVerificationSucceeded::class => 'handleSucceeded',
            IdentityVerificationFailed::class => 'handleFailed',
            IdentityManualReviewSubmitted::class => 'handleManualReviewSubmitted',
            IdentityManualReviewApproved::class => 'handleManualReviewApproved',
            IdentityManualReviewRejected::class => 'handleManualReviewRejected',
            EmployeeIdentityOverridden::class => 'handleOverridden',
        ];
    }
}
