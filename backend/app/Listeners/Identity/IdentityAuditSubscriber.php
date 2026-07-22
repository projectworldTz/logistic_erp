<?php

namespace App\Listeners\Identity;

use App\Events\Identity\EmployeeIdentityConfirmed;
use App\Events\Identity\EmployeeIdentityOverridden;
use App\Events\Identity\EmployeeIdentityRejected;
use App\Events\Identity\IdentityManualReviewApproved;
use App\Events\Identity\IdentityManualReviewRejected;
use App\Events\Identity\IdentityManualReviewSubmitted;
use App\Events\Identity\IdentityVerificationFailed;
use App\Events\Identity\IdentityVerificationSucceeded;
use App\Services\Audit\AuditLogger;
use Illuminate\Events\Dispatcher;

/**
 * Every identity-verification lifecycle event lands in the generic
 * AuditLog too, alongside the richer EmployeeIdentityVerification /
 * EmployeeIdentityManualReview rows those events already carry — this is
 * what makes the events findable from the same Audit Log screen every
 * other module's activity shows up in. Only masked identifiers are ever
 * written here.
 */
class IdentityAuditSubscriber
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handleSucceeded(IdentityVerificationSucceeded $event): void
    {
        $verification = $event->verification;

        $this->auditLogger->log(
            action: 'identity_verification.succeeded',
            auditable: $verification,
            newValues: [
                'status' => $verification->verification_status->value,
                'provider' => $verification->provider,
                'identity_number_masked' => $verification->identity_number_masked,
            ],
            tenantId: $verification->tenant_id,
            userId: $verification->requested_by,
        );
    }

    public function handleFailed(IdentityVerificationFailed $event): void
    {
        $verification = $event->verification;

        $this->auditLogger->log(
            action: 'identity_verification.failed',
            auditable: $verification,
            newValues: [
                'status' => $verification->verification_status->value,
                'reason' => $event->reason,
                'identity_number_masked' => $verification->identity_number_masked,
            ],
            tenantId: $verification->tenant_id,
            userId: $verification->requested_by,
        );
    }

    public function handleConfirmed(EmployeeIdentityConfirmed $event): void
    {
        $this->auditLogger->log(
            action: 'identity_verification.confirmed',
            auditable: $event->verification,
            newValues: ['confirmed_by' => $event->confirmedBy->id],
            tenantId: $event->verification->tenant_id,
            userId: $event->confirmedBy->id,
        );
    }

    public function handleRejected(EmployeeIdentityRejected $event): void
    {
        $this->auditLogger->log(
            action: 'identity_verification.rejected',
            auditable: $event->verification,
            newValues: ['rejected_by' => $event->rejectedBy->id],
            tenantId: $event->verification->tenant_id,
            userId: $event->rejectedBy->id,
        );
    }

    public function handleOverridden(EmployeeIdentityOverridden $event): void
    {
        $this->auditLogger->log(
            action: 'identity_verification.overridden',
            auditable: $event->employee,
            newValues: ['reason' => $event->reason],
            tenantId: $event->employee->tenant_id,
            userId: $event->overriddenBy->id,
        );
    }

    public function handleManualReviewSubmitted(IdentityManualReviewSubmitted $event): void
    {
        $this->auditLogger->log(
            action: 'identity_manual_review.submitted',
            auditable: $event->review,
            newValues: ['reason' => $event->review->reason],
            tenantId: $event->review->tenant_id,
            userId: $event->review->submitted_by,
        );
    }

    public function handleManualReviewApproved(IdentityManualReviewApproved $event): void
    {
        $this->auditLogger->log(
            action: 'identity_manual_review.approved',
            auditable: $event->review,
            tenantId: $event->review->tenant_id,
            userId: $event->review->reviewed_by,
        );
    }

    public function handleManualReviewRejected(IdentityManualReviewRejected $event): void
    {
        $this->auditLogger->log(
            action: 'identity_manual_review.rejected',
            auditable: $event->review,
            tenantId: $event->review->tenant_id,
            userId: $event->review->reviewed_by,
        );
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            IdentityVerificationSucceeded::class => 'handleSucceeded',
            IdentityVerificationFailed::class => 'handleFailed',
            EmployeeIdentityConfirmed::class => 'handleConfirmed',
            EmployeeIdentityRejected::class => 'handleRejected',
            EmployeeIdentityOverridden::class => 'handleOverridden',
            IdentityManualReviewSubmitted::class => 'handleManualReviewSubmitted',
            IdentityManualReviewApproved::class => 'handleManualReviewApproved',
            IdentityManualReviewRejected::class => 'handleManualReviewRejected',
        ];
    }
}
