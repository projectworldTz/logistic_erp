<?php

namespace App\Services\Identity;

use App\Enums\IdentityManualReviewStatus;
use App\Enums\IdentityVerificationStatus;
use App\Events\Identity\IdentityManualReviewApproved;
use App\Events\Identity\IdentityManualReviewRejected;
use App\Events\Identity\IdentityManualReviewSubmitted;
use App\Models\Employee;
use App\Models\EmployeeIdentityManualReview;
use App\Models\EmployeeIdentityVerification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class IdentityManualReviewService
{
    public function submit(
        User $actor,
        string $reason,
        ?string $notes = null,
        ?string $supportingDocumentType = null,
        ?string $supportingDocumentNumber = null,
        ?UploadedFile $file = null,
        ?EmployeeIdentityVerification $verification = null,
        ?Employee $employee = null,
    ): EmployeeIdentityManualReview {
        $path = $file?->storeAs(
            'identity-manual-reviews',
            Str::random(20).'.'.$file->getClientOriginalExtension(),
            'local',
        );

        $review = EmployeeIdentityManualReview::query()->create([
            'employee_id' => $employee?->id,
            'verification_id' => $verification?->id,
            'submitted_by' => $actor->id,
            'status' => IdentityManualReviewStatus::Pending->value,
            'reason' => $reason,
            'notes' => $notes,
            'supporting_document_type' => $supportingDocumentType,
            'supporting_document_number' => $supportingDocumentNumber,
            'supporting_document_path' => $path,
            'submitted_at' => now(),
        ]);

        $verification?->update(['verification_status' => IdentityVerificationStatus::RequiresReview->value]);

        event(new IdentityManualReviewSubmitted($review));

        return $review;
    }

    public function approve(EmployeeIdentityManualReview $review, User $reviewer, ?string $reviewerNotes = null): EmployeeIdentityManualReview
    {
        $this->guardPending($review);

        $review->update([
            'status' => IdentityManualReviewStatus::Approved->value,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'reviewer_notes' => $reviewerNotes,
        ]);

        $review->verification?->update(['verification_status' => IdentityVerificationStatus::ManuallyVerified->value]);

        if ($review->employee) {
            $review->employee->forceFill([
                'identity_verification_status' => IdentityVerificationStatus::ManuallyVerified->value,
                'identity_verified' => true,
                'identity_verified_at' => now(),
                'identity_verified_by' => $reviewer->id,
                'identity_last_synced_at' => now(),
            ])->save();
        }

        event(new IdentityManualReviewApproved($review->fresh()));

        return $review->fresh();
    }

    public function reject(EmployeeIdentityManualReview $review, User $reviewer, ?string $reviewerNotes = null): EmployeeIdentityManualReview
    {
        $this->guardPending($review);

        $review->update([
            'status' => IdentityManualReviewStatus::Rejected->value,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'reviewer_notes' => $reviewerNotes,
        ]);

        $review->verification?->update(['verification_status' => IdentityVerificationStatus::Rejected->value]);

        event(new IdentityManualReviewRejected($review->fresh()));

        return $review->fresh();
    }

    public function downloadPath(EmployeeIdentityManualReview $review): string
    {
        if (! $review->supporting_document_path || ! Storage::disk('local')->exists($review->supporting_document_path)) {
            throw new RuntimeException('No supporting document is attached to this review.');
        }

        return $review->supporting_document_path;
    }

    private function guardPending(EmployeeIdentityManualReview $review): void
    {
        if ($review->status !== IdentityManualReviewStatus::Pending) {
            throw new RuntimeException('This manual review has already been decided.');
        }
    }
}
