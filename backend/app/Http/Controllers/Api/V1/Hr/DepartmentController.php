<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreDepartmentRequest;
use App\Http\Requests\Hr\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;

class DepartmentController extends Controller
{
    public function index()
    {
        return DepartmentResource::collection(
            Department::query()->with('branch')->withCount('employees')->orderBy('name')->get()
        );
    }

    public function store(StoreDepartmentRequest $request)
    {
        $department = Department::query()->create($request->validated())->refresh();

        return new DepartmentResource($department->load('branch'));
    }

    public function show(Department $department)
    {
        return new DepartmentResource($department->load('branch')->loadCount('employees'));
    }

    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        $department->update($request->validated());

        return new DepartmentResource($department->load('branch'));
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return response()->json(status: 204);
    }
}
