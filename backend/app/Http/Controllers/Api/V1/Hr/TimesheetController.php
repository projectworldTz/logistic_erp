<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\TimesheetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreTimesheetRequest;
use App\Http\Requests\Hr\UpdateTimesheetRequest;
use App\Http\Resources\TimesheetResource;
use App\Models\Timesheet;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimesheetController extends Controller
{
    private const WITH = ['employee', 'customer', 'department', 'approver'];

    public function index(Request $request)
    {
        return TimesheetResource::collection(
            Timesheet::query()
                ->with(self::WITH)
                ->when($request->query('employee_id'), fn ($query, $id) => $query->where('employee_id', $id))
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest('date')
                ->paginate(30)
        );
    }

    public function store(StoreTimesheetRequest $request)
    {
        $timesheet = Timesheet::query()->create($request->validated())->refresh();

        return new TimesheetResource($timesheet->load(self::WITH));
    }

    public function show(Timesheet $timesheet)
    {
        return new TimesheetResource($timesheet->load(self::WITH));
    }

    public function update(UpdateTimesheetRequest $request, Timesheet $timesheet)
    {
        abort_if($timesheet->status !== TimesheetStatus::Pending, 409, 'Only pending timesheets can be edited.');

        $timesheet->update($request->validated());

        return new TimesheetResource($timesheet->load(self::WITH));
    }

    public function destroy(Timesheet $timesheet)
    {
        abort_if($timesheet->status !== TimesheetStatus::Pending, 409, 'Only pending timesheets can be deleted.');

        $timesheet->delete();

        return response()->json(status: 204);
    }

    public function approve(Timesheet $timesheet, AuditLogger $auditLogger)
    {
        abort_if($timesheet->status !== TimesheetStatus::Pending, 409, 'Only pending timesheets can be approved.');

        $timesheet->update(['status' => TimesheetStatus::Approved, 'approved_by' => Auth::id()]);

        $auditLogger->log('timesheet.approved', $timesheet, tenantId: $timesheet->tenant_id);

        return new TimesheetResource($timesheet->load(self::WITH));
    }

    public function reject(Timesheet $timesheet, AuditLogger $auditLogger)
    {
        abort_if($timesheet->status !== TimesheetStatus::Pending, 409, 'Only pending timesheets can be rejected.');

        $timesheet->update(['status' => TimesheetStatus::Rejected, 'approved_by' => Auth::id()]);

        $auditLogger->log('timesheet.rejected', $timesheet, tenantId: $timesheet->tenant_id);

        return new TimesheetResource($timesheet->load(self::WITH));
    }
}
