<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeeRequest;
use App\Http\Requests\Hr\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        return EmployeeResource::collection(
            Employee::query()
                ->with(['department', 'branch'])
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->when($request->query('department_id'), fn ($query, $id) => $query->where('department_id', $id))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreEmployeeRequest $request)
    {
        $employee = Employee::query()->create($request->validated())->refresh();

        return new EmployeeResource($employee->load(['department', 'branch']));
    }

    public function show(Employee $employee)
    {
        return new EmployeeResource($employee->load(['department', 'branch', 'user']));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());

        return new EmployeeResource($employee->load(['department', 'branch']));
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return response()->json(status: 204);
    }
}
