<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\ApprovalRequestStatus;
use App\Enums\LoanStatus;
use App\Enums\WorkflowSubjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RejectEmployeeLoanRequest;
use App\Http\Requests\Hr\StoreEmployeeLoanRequest;
use App\Http\Resources\EmployeeLoanResource;
use App\Models\EmployeeLoan;
use App\Services\Payroll\LoanService;
use App\Services\Payroll\PayrollMath;
use App\Services\Workflow\ApprovalEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeLoanController extends Controller
{
    private const WITH = ['employee', 'schedules', 'latestApprovalRequest.workflow.steps', 'latestApprovalRequest.decisions.decidedBy'];

    public function index(Request $request)
    {
        return EmployeeLoanResource::collection(
            EmployeeLoan::query()
                ->with(self::WITH)
                ->when($request->query('employee_id'), fn ($query, $id) => $query->where('employee_id', $id))
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreEmployeeLoanRequest $request)
    {
        $data = $request->validated();
        $principal = (string) $data['principal_amount'];
        $interestRate = (string) ($data['interest_rate'] ?? '0');
        $installments = (int) $data['number_of_installments'];

        $totalRepayable = PayrollMath::add($principal, PayrollMath::percent($principal, $interestRate));
        $installmentAmount = PayrollMath::money(PayrollMath::div($totalRepayable, (string) $installments));

        $loan = EmployeeLoan::query()->create([
            ...$data,
            'installment_amount' => $installmentAmount,
            'created_by' => Auth::id(),
        ])->refresh();

        return new EmployeeLoanResource($loan->load(self::WITH));
    }

    public function show(EmployeeLoan $employeeLoan)
    {
        return new EmployeeLoanResource($employeeLoan->load(self::WITH));
    }

    public function destroy(EmployeeLoan $employeeLoan)
    {
        abort_if($employeeLoan->status !== LoanStatus::Draft, 409, 'Only draft loans can be deleted.');

        $employeeLoan->delete();

        return response()->json(status: 204);
    }

    public function submit(EmployeeLoan $employeeLoan, LoanService $service, ApprovalEngine $engine)
    {
        $loan = $service->submit($employeeLoan);

        $engine->start($loan, WorkflowSubjectType::EmployeeLoan->value, (float) $loan->principal_amount);

        return new EmployeeLoanResource($loan->load(self::WITH));
    }

    public function approve(EmployeeLoan $employeeLoan, LoanService $service, ApprovalEngine $engine)
    {
        $pending = $engine->findPendingRequestFor($employeeLoan);

        if ($pending) {
            $decided = $engine->decide($pending, Auth::user(), true);

            if ($decided->status === ApprovalRequestStatus::Approved) {
                $service->approve($employeeLoan);
            }
        } else {
            abort_unless(Auth::user()->can('hr.loans.approve'), 403);
            $service->approve($employeeLoan);
        }

        return new EmployeeLoanResource($employeeLoan->fresh()->load(self::WITH));
    }

    public function reject(RejectEmployeeLoanRequest $request, EmployeeLoan $employeeLoan, LoanService $service, ApprovalEngine $engine)
    {
        $reason = $request->validated('reason');
        $pending = $engine->findPendingRequestFor($employeeLoan);

        if ($pending) {
            $engine->decide($pending, Auth::user(), false, $reason);
            $service->reject($employeeLoan, $reason);
        } else {
            abort_unless(Auth::user()->can('hr.loans.approve'), 403);
            $service->reject($employeeLoan, $reason);
        }

        return new EmployeeLoanResource($employeeLoan->fresh()->load(self::WITH));
    }
}
