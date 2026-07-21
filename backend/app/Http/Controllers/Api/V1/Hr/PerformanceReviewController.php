<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\PerformanceReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\AcknowledgePerformanceReviewRequest;
use App\Http\Requests\Hr\StorePerformanceReviewRequest;
use App\Http\Resources\PerformanceReviewResource;
use App\Models\Employee;
use App\Models\PerformanceReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PerformanceReviewController extends Controller
{
    private const WITH = ['employee', 'reviewer'];

    public function index(Request $request)
    {
        $canViewAll = Auth::user()->can('hr.performance.view.all');
        $ownEmployee = Employee::query()->where('user_id', Auth::id())->first();

        return PerformanceReviewResource::collection(
            PerformanceReview::query()
                ->with(self::WITH)
                ->when($request->query('employee_id'), fn ($query, $id) => $query->where('employee_id', $id))
                ->when(! $canViewAll, fn ($query) => $query->where('employee_id', $ownEmployee?->id ?? 0))
                ->latest('review_date')
                ->paginate(20)
        );
    }

    public function store(StorePerformanceReviewRequest $request)
    {
        $review = PerformanceReview::query()->create([
            ...$request->validated(),
            'reviewer_id' => Auth::id(),
            'created_by' => Auth::id(),
        ])->refresh();

        return new PerformanceReviewResource($review->load(self::WITH));
    }

    public function show(PerformanceReview $performanceReview)
    {
        return new PerformanceReviewResource($performanceReview->load(self::WITH));
    }

    public function submit(PerformanceReview $performanceReview)
    {
        abort_if($performanceReview->status !== PerformanceReviewStatus::Draft, 409, 'Only draft reviews can be submitted.');

        $performanceReview->update(['status' => PerformanceReviewStatus::Submitted]);

        return new PerformanceReviewResource($performanceReview->fresh()->load(self::WITH));
    }

    public function acknowledge(AcknowledgePerformanceReviewRequest $request, PerformanceReview $performanceReview)
    {
        abort_if($performanceReview->status !== PerformanceReviewStatus::Submitted, 409, 'Only submitted reviews can be acknowledged.');

        $ownEmployee = Employee::query()->where('user_id', Auth::id())->first();
        abort_unless($ownEmployee?->id === $performanceReview->employee_id, 403, 'Only the reviewed employee can acknowledge this review.');

        $performanceReview->update([
            'status' => PerformanceReviewStatus::Acknowledged,
            'employee_comments' => $request->validated('employee_comments'),
            'acknowledged_at' => now(),
        ]);

        return new PerformanceReviewResource($performanceReview->fresh()->load(self::WITH));
    }

    public function destroy(PerformanceReview $performanceReview)
    {
        abort_if($performanceReview->status !== PerformanceReviewStatus::Draft, 409, 'Only draft reviews can be deleted.');

        $performanceReview->delete();

        return response()->json(status: 204);
    }
}
