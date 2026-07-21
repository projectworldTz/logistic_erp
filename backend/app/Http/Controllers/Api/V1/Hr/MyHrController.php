<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\WorkflowSubjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreMyLeaveRequestRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Http\Resources\EmployeeAssetResource;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\LeaveBalanceResource;
use App\Http\Resources\LeaveRequestResource;
use App\Http\Resources\LeaveTypeResource;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Hr\LeaveRequestService;
use App\Services\Workflow\ApprovalEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

/**
 * Employee self-service surface. Every method here hard-scopes to the
 * caller's own Employee record (matched via Employee.user_id) rather than
 * accepting an employee_id from the request — the restricted "Employee"
 * role has none of the broad hr.* view/manage permissions, so this is the
 * only way that role can see or act on HR data at all. Mirrors the
 * existing self-service pattern from PayslipController/PerformanceReview
 * (no route-level permission middleware, internal ownership check) rather
 * than introducing a new authorization model.
 */
class MyHrController extends Controller
{
    private const LEAVE_REQUEST_WITH = ['employee', 'leaveType', 'creator', 'approver', 'latestApprovalRequest.workflow.steps', 'latestApprovalRequest.decisions.decidedBy'];

    private function ownEmployeeOrFail(): Employee
    {
        $employee = Employee::query()->where('user_id', Auth::id())->first();

        abort_if(! $employee, 404, 'No employee record is linked to your account.');

        return $employee;
    }

    public function profile()
    {
        $employee = $this->ownEmployeeOrFail();

        return new EmployeeResource($employee->load(['department', 'branch', 'designation', 'reportingManager']));
    }

    public function attendance(Request $request)
    {
        $employee = $this->ownEmployeeOrFail();

        return AttendanceRecordResource::collection(
            AttendanceRecord::query()
                ->with('employee')
                ->where('employee_id', $employee->id)
                ->when($request->query('from_date'), fn ($query, $date) => $query->where('date', '>=', $date))
                ->when($request->query('to_date'), fn ($query, $date) => $query->where('date', '<=', $date))
                ->latest('date')
                ->paginate(30)
        );
    }

    /**
     * Active leave type catalog — non-sensitive tenant-wide configuration
     * (just names/rules, not per-employee data), exposed here so a
     * self-service employee can populate the leave-type picker without
     * needing the broad hr.leave.view permission just to read it.
     */
    public function leaveTypes()
    {
        $this->ownEmployeeOrFail();

        return LeaveTypeResource::collection(
            LeaveType::query()->where('is_active', true)->orderBy('name')->get()
        );
    }

    public function leaveBalances(Request $request)
    {
        $employee = $this->ownEmployeeOrFail();

        return LeaveBalanceResource::collection(
            LeaveBalance::query()
                ->with(['employee', 'leaveType'])
                ->where('employee_id', $employee->id)
                ->when($request->query('year'), fn ($query, $year) => $query->where('year', $year), fn ($query) => $query->where('year', now()->year))
                ->get()
        );
    }

    public function leaveRequests()
    {
        $employee = $this->ownEmployeeOrFail();

        return LeaveRequestResource::collection(
            LeaveRequest::query()
                ->with(self::LEAVE_REQUEST_WITH)
                ->where('employee_id', $employee->id)
                ->latest('start_date')
                ->paginate(30)
        );
    }

    public function storeLeaveRequest(StoreMyLeaveRequestRequest $request, ApprovalEngine $engine)
    {
        $employee = $this->ownEmployeeOrFail();

        $start = Carbon::parse($request->validated('start_date'));
        $end = Carbon::parse($request->validated('end_date'));
        $days = $request->boolean('half_day') ? 0.5 : $start->diffInDays($end, absolute: true) + 1;

        $leaveRequest = LeaveRequest::query()->create([
            ...$request->validated(),
            'employee_id' => $employee->id,
            'days' => $days,
            'created_by' => Auth::id(),
        ])->refresh();

        $engine->start($leaveRequest, WorkflowSubjectType::LeaveRequest->value, null);

        return new LeaveRequestResource($leaveRequest->load(self::LEAVE_REQUEST_WITH));
    }

    public function cancelLeaveRequest(LeaveRequest $leaveRequest, LeaveRequestService $service)
    {
        $employee = $this->ownEmployeeOrFail();

        abort_if($leaveRequest->employee_id !== $employee->id, 403);

        return new LeaveRequestResource($service->cancel($leaveRequest)->load(self::LEAVE_REQUEST_WITH));
    }

    public function assets(Request $request)
    {
        $employee = $this->ownEmployeeOrFail();

        return EmployeeAssetResource::collection(
            EmployeeAsset::query()
                ->with('employee')
                ->where('employee_id', $employee->id)
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest('assigned_date')
                ->paginate(20)
        );
    }
}
