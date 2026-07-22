<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Events\Identity\EmployeeIdentityOverridden;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeeRequest;
use App\Http\Requests\Hr\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\EmployeeIdentityVerification;
use App\Services\Identity\IdentityVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    private const WITH = ['department', 'branch', 'designation', 'reportingManager'];

    /**
     * Fields sourced from a confirmed identity verification. Editing any
     * of these on an already identity_verified employee requires an
     * override reason — see update().
     */
    private const IDENTITY_SOURCED_FIELDS = ['first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender', 'nationality'];

    public function index(Request $request)
    {
        return EmployeeResource::collection(
            Employee::query()
                ->with(self::WITH)
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->when($request->query('department_id'), fn ($query, $id) => $query->where('department_id', $id))
                ->when($request->query('designation_id'), fn ($query, $id) => $query->where('designation_id', $id))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreEmployeeRequest $request, IdentityVerificationService $service)
    {
        $data = $request->validated();
        $verificationId = $data['identity_verification_id'] ?? null;
        unset($data['identity_verification_id']);

        $employee = DB::transaction(function () use ($data, $verificationId, $service) {
            $employee = Employee::query()->create($data)->refresh();

            if ($verificationId) {
                $verification = EmployeeIdentityVerification::query()->findOrFail($verificationId);
                abort_if(
                    $verification->employee_id !== null && $verification->employee_id !== $employee->id,
                    409,
                    'This identity verification is already linked to a different employee.'
                );
                $service->applyToEmployee($verification, $employee);
                $employee->refresh();
            }

            return $employee;
        });

        return new EmployeeResource($employee->load(self::WITH));
    }

    public function show(Employee $employee)
    {
        return new EmployeeResource($employee->load([...self::WITH, 'user']));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $data = $request->validated();
        $overrideReason = $data['identity_override_reason'] ?? null;
        unset($data['identity_override_reason']);

        $touchesIdentityFields = ! empty(array_intersect(self::IDENTITY_SOURCED_FIELDS, array_keys($data)));

        if ($employee->identity_verified && $touchesIdentityFields) {
            abort_if(! $overrideReason, 422, 'An override reason is required to change identity-sourced fields on a verified employee.');

            $data['identity_override_reason'] = $overrideReason;
            $data['identity_overridden_by'] = Auth::id();
            $data['identity_overridden_at'] = now();
        }

        $employee->update($data);

        if ($employee->identity_verified && $touchesIdentityFields) {
            event(new EmployeeIdentityOverridden($employee, Auth::user(), $overrideReason));
        }

        return new EmployeeResource($employee->load(self::WITH));
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return response()->json(status: 204);
    }
}
