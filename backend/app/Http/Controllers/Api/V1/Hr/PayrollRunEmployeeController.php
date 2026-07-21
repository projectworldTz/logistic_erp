<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\PayrollRunStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\UpdatePayrollRunEmployeeRequest;
use App\Http\Resources\PayrollRunEmployeeResource;
use App\Models\PayrollRunEmployee;
use App\Services\Payroll\PayrollCalculationService;

class PayrollRunEmployeeController extends Controller
{
    public function update(UpdatePayrollRunEmployeeRequest $request, PayrollRunEmployee $payrollRunEmployee, PayrollCalculationService $service)
    {
        $run = $payrollRunEmployee->payrollRun;
        abort_if(
            ! in_array($run->status, [PayrollRunStatus::Calculated], true),
            409,
            'Employees can only be included/excluded on a calculated run before it is submitted for approval.',
        );
        abort_if($payrollRunEmployee->status->value === 'exception', 422, 'Resolve the exception before changing this employee\'s inclusion status.');

        $payrollRunEmployee->update(['status' => $request->validated('status')]);
        $service->recomputeTotals($run);

        return new PayrollRunEmployeeResource($payrollRunEmployee->fresh()->load(['employee', 'earnings', 'deductions', 'employerContributions']));
    }
}
