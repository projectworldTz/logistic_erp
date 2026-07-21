<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StorePayrollPeriodRequest;
use App\Http\Resources\PayrollPeriodResource;
use App\Models\PayrollPeriod;

class PayrollPeriodController extends Controller
{
    public function index()
    {
        return PayrollPeriodResource::collection(
            PayrollPeriod::query()->with('runs')->orderByDesc('period_start')->get()
        );
    }

    public function store(StorePayrollPeriodRequest $request)
    {
        $period = PayrollPeriod::query()->create($request->validated())->refresh();

        return new PayrollPeriodResource($period);
    }

    public function show(PayrollPeriod $payrollPeriod)
    {
        return new PayrollPeriodResource($payrollPeriod->load('runs'));
    }

    public function destroy(PayrollPeriod $payrollPeriod)
    {
        abort_if($payrollPeriod->runs()->exists(), 409, 'This period already has payroll runs and cannot be deleted.');

        $payrollPeriod->delete();

        return response()->json(status: 204);
    }
}
