<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreLeaveTypeRequest;
use App\Http\Requests\Hr\UpdateLeaveTypeRequest;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function index(Request $request)
    {
        return LeaveTypeResource::collection(
            LeaveType::query()
                ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get()
        );
    }

    public function store(StoreLeaveTypeRequest $request)
    {
        $leaveType = LeaveType::query()->create($request->validated())->refresh();

        return new LeaveTypeResource($leaveType);
    }

    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType)
    {
        $leaveType->update($request->validated());

        return new LeaveTypeResource($leaveType);
    }

    public function destroy(LeaveType $leaveType)
    {
        $leaveType->delete();

        return response()->json(status: 204);
    }
}
