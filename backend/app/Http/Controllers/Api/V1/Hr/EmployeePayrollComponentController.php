<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeePayrollComponentRequest;
use App\Http\Resources\EmployeePayrollComponentResource;
use App\Models\EmployeePayrollComponent;
use Illuminate\Http\Request;

class EmployeePayrollComponentController extends Controller
{
    private const WITH = ['employee', 'payrollComponent'];

    public function index(Request $request)
    {
        return EmployeePayrollComponentResource::collection(
            EmployeePayrollComponent::query()
                ->with(self::WITH)
                ->when($request->query('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
                ->orderByDesc('effective_date')
                ->get()
        );
    }

    public function store(StoreEmployeePayrollComponentRequest $request)
    {
        $assignment = EmployeePayrollComponent::query()->create($request->validated())->refresh();

        return new EmployeePayrollComponentResource($assignment->load(self::WITH));
    }

    public function destroy(EmployeePayrollComponent $employeePayrollComponent)
    {
        $employeePayrollComponent->delete();

        return response()->json(status: 204);
    }
}
