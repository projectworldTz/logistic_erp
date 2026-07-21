<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\ApprovalRequestStatus;
use App\Enums\WorkflowSubjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RejectLeaveRequestRequest;
use App\Http\Requests\Hr\StoreLeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Services\Hr\LeaveRequestService;
use App\Services\Workflow\ApprovalEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class LeaveRequestController extends Controller
{
    private const WITH = ['employee', 'leaveType', 'creator', 'approver', 'latestApprovalRequest.workflow.steps', 'latestApprovalRequest.decisions.decidedBy'];

    public function index(Request $request)
    {
        return LeaveRequestResource::collection(
            LeaveRequest::query()
                ->with(self::WITH)
                ->when($request->query('employee_id'), fn ($query, $id) => $query->where('employee_id', $id))
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest('start_date')
                ->paginate(30)
        );
    }

    public function store(StoreLeaveRequestRequest $request, ApprovalEngine $engine)
    {
        $start = Carbon::parse($request->validated('start_date'));
        $end = Carbon::parse($request->validated('end_date'));
        // Inclusive calendar-day count (end - start + 1); weekday/holiday-aware
        // accrual rules are a Phase 3+ statutory-engine concern, not this MVP.
        $days = $request->boolean('half_day') ? 0.5 : $start->diffInDays($end, absolute: true) + 1;

        $leaveRequest = LeaveRequest::query()->create([
            ...$request->validated(),
            'days' => $days,
            'created_by' => Auth::id(),
        ])->refresh();

        $engine->start($leaveRequest, WorkflowSubjectType::LeaveRequest->value, null);

        return new LeaveRequestResource($leaveRequest->load(self::WITH));
    }

    public function show(LeaveRequest $leaveRequest)
    {
        return new LeaveRequestResource($leaveRequest->load(self::WITH));
    }

    public function approve(LeaveRequest $leaveRequest, LeaveRequestService $service, ApprovalEngine $engine)
    {
        $pending = $engine->findPendingRequestFor($leaveRequest);

        if ($pending) {
            $decided = $engine->decide($pending, Auth::user(), true);

            if ($decided->status === ApprovalRequestStatus::Approved) {
                $service->approve($leaveRequest);
            }
        } else {
            abort_unless(Auth::user()->can('hr.leave.approve'), 403);
            $service->approve($leaveRequest);
        }

        return new LeaveRequestResource($leaveRequest->fresh()->load(self::WITH));
    }

    public function reject(RejectLeaveRequestRequest $request, LeaveRequest $leaveRequest, LeaveRequestService $service, ApprovalEngine $engine)
    {
        $reason = $request->validated('reason');
        $pending = $engine->findPendingRequestFor($leaveRequest);

        if ($pending) {
            $engine->decide($pending, Auth::user(), false, $reason);
            $service->reject($leaveRequest, $reason);
        } else {
            abort_unless(Auth::user()->can('hr.leave.approve'), 403);
            $service->reject($leaveRequest, $reason);
        }

        return new LeaveRequestResource($leaveRequest->fresh()->load(self::WITH));
    }

    public function cancel(LeaveRequest $leaveRequest, LeaveRequestService $service)
    {
        return new LeaveRequestResource($service->cancel($leaveRequest)->load(self::WITH));
    }
}
