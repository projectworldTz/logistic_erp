<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\ApprovalRequestStatus;
use App\Enums\SalaryAdvanceStatus;
use App\Enums\WorkflowSubjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RejectSalaryAdvanceRequest;
use App\Http\Requests\Hr\StoreSalaryAdvanceRequest;
use App\Http\Resources\SalaryAdvanceResource;
use App\Models\SalaryAdvance;
use App\Services\Payroll\PayrollMath;
use App\Services\Payroll\SalaryAdvanceService;
use App\Services\Workflow\ApprovalEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalaryAdvanceController extends Controller
{
    private const WITH = ['employee', 'schedules', 'latestApprovalRequest.workflow.steps', 'latestApprovalRequest.decisions.decidedBy'];

    public function index(Request $request)
    {
        return SalaryAdvanceResource::collection(
            SalaryAdvance::query()
                ->with(self::WITH)
                ->when($request->query('employee_id'), fn ($query, $id) => $query->where('employee_id', $id))
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreSalaryAdvanceRequest $request)
    {
        $data = $request->validated();
        $installmentAmount = PayrollMath::money(PayrollMath::div((string) $data['amount'], (string) $data['number_of_installments']));

        $advance = SalaryAdvance::query()->create([
            ...$data,
            'installment_amount' => $installmentAmount,
            'created_by' => Auth::id(),
        ])->refresh();

        return new SalaryAdvanceResource($advance->load(self::WITH));
    }

    public function show(SalaryAdvance $salaryAdvance)
    {
        return new SalaryAdvanceResource($salaryAdvance->load(self::WITH));
    }

    public function destroy(SalaryAdvance $salaryAdvance)
    {
        abort_if($salaryAdvance->status !== SalaryAdvanceStatus::Draft, 409, 'Only draft advances can be deleted.');

        $salaryAdvance->delete();

        return response()->json(status: 204);
    }

    public function submit(SalaryAdvance $salaryAdvance, SalaryAdvanceService $service, ApprovalEngine $engine)
    {
        $advance = $service->submit($salaryAdvance);

        $engine->start($advance, WorkflowSubjectType::SalaryAdvance->value, (float) $advance->amount);

        return new SalaryAdvanceResource($advance->load(self::WITH));
    }

    public function approve(SalaryAdvance $salaryAdvance, SalaryAdvanceService $service, ApprovalEngine $engine)
    {
        $pending = $engine->findPendingRequestFor($salaryAdvance);

        if ($pending) {
            $decided = $engine->decide($pending, Auth::user(), true);

            if ($decided->status === ApprovalRequestStatus::Approved) {
                $service->approve($salaryAdvance);
            }
        } else {
            abort_unless(Auth::user()->can('hr.advances.approve'), 403);
            $service->approve($salaryAdvance);
        }

        return new SalaryAdvanceResource($salaryAdvance->fresh()->load(self::WITH));
    }

    public function reject(RejectSalaryAdvanceRequest $request, SalaryAdvance $salaryAdvance, SalaryAdvanceService $service, ApprovalEngine $engine)
    {
        $reason = $request->validated('reason');
        $pending = $engine->findPendingRequestFor($salaryAdvance);

        if ($pending) {
            $engine->decide($pending, Auth::user(), false, $reason);
            $service->reject($salaryAdvance, $reason);
        } else {
            abort_unless(Auth::user()->can('hr.advances.approve'), 403);
            $service->reject($salaryAdvance, $reason);
        }

        return new SalaryAdvanceResource($salaryAdvance->fresh()->load(self::WITH));
    }
}
