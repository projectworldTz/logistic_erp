<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreAttendanceRecordRequest;
use App\Http\Requests\Hr\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Illuminate\Http\Request;

class AttendanceRecordController extends Controller
{
    public function index(Request $request)
    {
        return AttendanceRecordResource::collection(
            AttendanceRecord::query()
                ->with('employee')
                ->when($request->query('employee_id'), fn ($query, $id) => $query->where('employee_id', $id))
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->when($request->query('from_date'), fn ($query, $date) => $query->where('date', '>=', $date))
                ->when($request->query('to_date'), fn ($query, $date) => $query->where('date', '<=', $date))
                ->latest('date')
                ->paginate(30)
        );
    }

    public function store(StoreAttendanceRecordRequest $request)
    {
        $record = AttendanceRecord::query()->create($request->validated())->refresh();

        return new AttendanceRecordResource($record->load('employee'));
    }

    public function show(AttendanceRecord $attendanceRecord)
    {
        return new AttendanceRecordResource($attendanceRecord->load('employee'));
    }

    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord)
    {
        $attendanceRecord->update($request->validated());

        return new AttendanceRecordResource($attendanceRecord->load('employee'));
    }

    public function destroy(AttendanceRecord $attendanceRecord)
    {
        $attendanceRecord->delete();

        return response()->json(status: 204);
    }
}
