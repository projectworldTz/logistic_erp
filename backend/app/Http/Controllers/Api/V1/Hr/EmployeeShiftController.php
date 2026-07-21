<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeeShiftRequest;
use App\Http\Resources\EmployeeShiftResource;
use App\Models\EmployeeShift;
use Illuminate\Http\Request;

class EmployeeShiftController extends Controller
{
    public function index(Request $request)
    {
        return EmployeeShiftResource::collection(
            EmployeeShift::query()
                ->with(['employee', 'shift'])
                ->when($request->query('employee_id'), fn ($query, $id) => $query->where('employee_id', $id))
                ->latest('effective_date')
                ->get()
        );
    }

    public function store(StoreEmployeeShiftRequest $request)
    {
        $assignment = EmployeeShift::query()->create($request->validated())->refresh();

        return new EmployeeShiftResource($assignment->load(['employee', 'shift']));
    }

    public function destroy(EmployeeShift $employeeShift)
    {
        $employeeShift->delete();

        return response()->json(status: 204);
    }
}
