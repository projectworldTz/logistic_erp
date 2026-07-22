<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\DecideIdentityManualReviewRequest;
use App\Http\Requests\Identity\SubmitIdentityManualReviewRequest;
use App\Http\Resources\EmployeeIdentityManualReviewResource;
use App\Models\Employee;
use App\Models\EmployeeIdentityManualReview;
use App\Models\EmployeeIdentityVerification;
use App\Services\Identity\IdentityManualReviewService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class IdentityManualReviewController extends Controller
{
    /**
     * Submit an existing verification for manual review. The verification
     * may or may not already be linked to an employee — this is reachable
     * both during registration (no employee yet) and for an existing
     * employee flagged for re-review.
     */
    public function store(SubmitIdentityManualReviewRequest $request, EmployeeIdentityVerification $verification, IdentityManualReviewService $service)
    {
        $review = $service->submit(
            actor: Auth::user(),
            reason: $request->validated('reason'),
            notes: $request->validated('notes'),
            supportingDocumentType: $request->validated('supporting_document_type'),
            supportingDocumentNumber: $request->validated('supporting_document_number'),
            file: $request->file('file'),
            verification: $verification,
            employee: $verification->employee,
        );

        return new EmployeeIdentityManualReviewResource($review);
    }

    public function index(Employee $employee)
    {
        return EmployeeIdentityManualReviewResource::collection(
            $employee->identityManualReviews()->with(['submittedBy', 'reviewedBy'])->latest()->get()
        );
    }

    public function approve(DecideIdentityManualReviewRequest $request, EmployeeIdentityManualReview $review, IdentityManualReviewService $service)
    {
        return new EmployeeIdentityManualReviewResource(
            $service->approve($review, Auth::user(), $request->validated('reviewer_notes'))
        );
    }

    public function reject(DecideIdentityManualReviewRequest $request, EmployeeIdentityManualReview $review, IdentityManualReviewService $service)
    {
        return new EmployeeIdentityManualReviewResource(
            $service->reject($review, Auth::user(), $request->validated('reviewer_notes'))
        );
    }

    /**
     * Only reachable via the short-lived signed URL
     * EmployeeIdentityManualReviewResource generates.
     */
    public function download(EmployeeIdentityManualReview $review, IdentityManualReviewService $service)
    {
        $path = $service->downloadPath($review);

        return Storage::disk('local')->download($path);
    }
}
