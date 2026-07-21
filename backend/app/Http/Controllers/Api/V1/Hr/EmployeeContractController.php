<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ContractStatus;
use App\Enums\WorkflowSubjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RejectEmployeeContractRequest;
use App\Http\Requests\Hr\StoreEmployeeContractRequest;
use App\Http\Requests\Hr\UpdateEmployeeContractRequest;
use App\Http\Resources\EmployeeContractResource;
use App\Models\EmployeeContract;
use App\Services\Hr\EmployeeContractService;
use App\Services\Workflow\ApprovalEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeContractController extends Controller
{
    private const WITH = ['employee', 'creator', 'approver', 'latestApprovalRequest.workflow.steps', 'latestApprovalRequest.decisions.decidedBy'];

    public function index(Request $request)
    {
        return EmployeeContractResource::collection(
            EmployeeContract::query()
                ->with(self::WITH)
                ->when($request->query('employee_id'), fn ($query, $id) => $query->where('employee_id', $id))
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest('effective_date')
                ->paginate(20)
        );
    }

    public function store(StoreEmployeeContractRequest $request)
    {
        $contract = EmployeeContract::query()->create([
            ...$request->validated(),
            'created_by' => Auth::id(),
        ])->refresh();

        return new EmployeeContractResource($contract->load(self::WITH));
    }

    public function show(EmployeeContract $employeeContract)
    {
        return new EmployeeContractResource($employeeContract->load(self::WITH));
    }

    public function update(UpdateEmployeeContractRequest $request, EmployeeContract $employeeContract)
    {
        abort_if($employeeContract->status !== ContractStatus::Draft, 409, 'Only draft contracts can be edited.');

        $employeeContract->update($request->validated());

        return new EmployeeContractResource($employeeContract->load(self::WITH));
    }

    public function destroy(EmployeeContract $employeeContract)
    {
        abort_if($employeeContract->status !== ContractStatus::Draft, 409, 'Only draft contracts can be deleted.');

        $employeeContract->delete();

        return response()->json(status: 204);
    }

    public function submit(EmployeeContract $employeeContract, EmployeeContractService $service, ApprovalEngine $engine)
    {
        $contract = $service->submit($employeeContract);

        $engine->start($contract, WorkflowSubjectType::EmployeeContract->value, (float) $contract->basic_salary);

        return new EmployeeContractResource($contract->load(self::WITH));
    }

    public function approve(EmployeeContract $employeeContract, EmployeeContractService $service, ApprovalEngine $engine)
    {
        $pending = $engine->findPendingRequestFor($employeeContract);

        if ($pending) {
            $decided = $engine->decide($pending, Auth::user(), true);

            if ($decided->status === ApprovalRequestStatus::Approved) {
                $service->approve($employeeContract);
            }
        } else {
            abort_unless(Auth::user()->can('hr.contracts.approve'), 403);
            $service->approve($employeeContract);
        }

        return new EmployeeContractResource($employeeContract->fresh()->load(self::WITH));
    }

    public function reject(RejectEmployeeContractRequest $request, EmployeeContract $employeeContract, EmployeeContractService $service, ApprovalEngine $engine)
    {
        $reason = $request->validated('reason');
        $pending = $engine->findPendingRequestFor($employeeContract);

        if ($pending) {
            $engine->decide($pending, Auth::user(), false, $reason);
            $service->reject($employeeContract, $reason);
        } else {
            abort_unless(Auth::user()->can('hr.contracts.approve'), 403);
            $service->reject($employeeContract, $reason);
        }

        return new EmployeeContractResource($employeeContract->fresh()->load(self::WITH));
    }
}
