<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RejectPayrollRunRequest;
use App\Http\Requests\Hr\StorePayrollRunRequest;
use App\Http\Resources\PayrollRunResource;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollApprovalService;
use App\Services\Payroll\PayrollCalculationService;
use App\Services\Payroll\PayrollPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayrollRunController extends Controller
{
    private const WITH = ['period', 'runEmployees.employee', 'payslips', 'salaryPaymentBatch', 'latestApprovalRequest.workflow.steps', 'latestApprovalRequest.decisions.decidedBy'];
    private const WITH_LIST = ['period'];

    public function index(Request $request)
    {
        return PayrollRunResource::collection(
            PayrollRun::query()
                ->with(self::WITH_LIST)
                ->withCount('runEmployees')
                ->when($request->query('payroll_period_id'), fn ($query, $id) => $query->where('payroll_period_id', $id))
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function store(StorePayrollRunRequest $request, PayrollPeriod $payrollPeriod)
    {
        abort_if($payrollPeriod->is_locked, 409, 'This payroll period is locked.');

        $nextRunNumber = $payrollPeriod->runs()->max('run_number') + 1;

        $run = PayrollRun::query()->create([
            'tenant_id' => $payrollPeriod->tenant_id,
            'payroll_period_id' => $payrollPeriod->id,
            'run_number' => $nextRunNumber,
            'statutory_rule_set_id' => $request->validated('statutory_rule_set_id'),
            'created_by' => Auth::id(),
        ])->refresh();

        return new PayrollRunResource($run->load(self::WITH_LIST));
    }

    public function show(PayrollRun $payrollRun)
    {
        return new PayrollRunResource($payrollRun->load(self::WITH));
    }

    public function calculate(PayrollRun $payrollRun, PayrollCalculationService $service)
    {
        $run = $service->calculate($payrollRun);

        return new PayrollRunResource($run->load(self::WITH));
    }

    public function submit(PayrollRun $payrollRun, PayrollApprovalService $service)
    {
        $run = $service->submit($payrollRun);

        return new PayrollRunResource($run->load(self::WITH));
    }

    public function approve(PayrollRun $payrollRun, PayrollApprovalService $service)
    {
        $run = $service->approve($payrollRun);

        return new PayrollRunResource($run->load(self::WITH));
    }

    public function reject(RejectPayrollRunRequest $request, PayrollRun $payrollRun, PayrollApprovalService $service)
    {
        $run = $service->reject($payrollRun, $request->validated('reason'));

        return new PayrollRunResource($run->load(self::WITH));
    }

    public function finalize(PayrollRun $payrollRun, PayrollApprovalService $service)
    {
        $run = $service->finalize($payrollRun);

        return new PayrollRunResource($run->load(self::WITH));
    }

    public function postToAccounting(PayrollRun $payrollRun, PayrollPostingService $service)
    {
        $run = $service->post($payrollRun);

        return new PayrollRunResource($run->load(self::WITH));
    }
}
