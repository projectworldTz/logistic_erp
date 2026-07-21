<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Models\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;

class PayslipVerificationController extends Controller
{
    /**
     * Public, unauthenticated payslip verification keyed off the
     * long random verification_code — possession of the code is the
     * authorization, the same pattern used for delivery-note/release-
     * order verification elsewhere in this codebase.
     */
    public function show(string $code): JsonResponse
    {
        $payslip = Payslip::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with('employee', 'payrollRun.period')
            ->where('verification_code', $code)
            ->first();

        abort_if(! $payslip, 404);

        return response()->json([
            'data' => [
                'payslip_number' => $payslip->payslip_number,
                'employee_name' => $payslip->employee?->name,
                'period_name' => $payslip->payrollRun->period?->name,
                'net_pay' => $payslip->net_pay,
                'generated_at' => $payslip->created_at,
            ],
        ]);
    }
}
