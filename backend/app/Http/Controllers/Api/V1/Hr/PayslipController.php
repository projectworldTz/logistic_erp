<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayslipResource;
use App\Models\Employee;
use App\Models\Payslip;
use App\Services\Tracking\QrCodeService;
use App\Support\Pdf\BrandColors;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayslipController extends Controller
{
    /**
     * Own payslips (employee self-service) or, with the staff
     * permission, any employee's — enforced the same way Phase 1's
     * salary sub-resource was: a separate check, not a field flag.
     */
    public function index(Request $request)
    {
        $employeeId = $request->query('employee_id');
        $ownEmployee = Employee::query()->where('user_id', Auth::id())->first();

        abort_if(
            $employeeId && (int) $employeeId !== $ownEmployee?->id && ! Auth::user()->can('hr.payslips.view.all'),
            403,
        );
        abort_if(! $employeeId && ! $ownEmployee && ! Auth::user()->can('hr.payslips.view.all'), 403);

        return PayslipResource::collection(
            Payslip::query()
                ->with('employee')
                ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
                ->when(! $employeeId && ! Auth::user()->can('hr.payslips.view.all'), fn ($query) => $query->where('employee_id', $ownEmployee?->id ?? 0))
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Payslip $payslip)
    {
        $this->authorizeAccess($payslip);

        return new PayslipResource($payslip->load(['employee', 'payrollRun.period']));
    }

    public function pdf(Payslip $payslip)
    {
        $this->authorizeAccess($payslip);

        $payslip->load(['employee.designation', 'payrollRun.period', 'payrollRunEmployee.earnings', 'payrollRunEmployee.deductions']);
        $company = \App\Models\Company::query()->firstOrFail();

        $verificationUrl = rtrim(config('app.frontend_url'), '/')."/verify/payslip/{$payslip->verification_code}";
        $qrDataUri = 'data:image/svg+xml;base64,'.base64_encode(app(QrCodeService::class)->svg($verificationUrl));

        $pdf = Pdf::loadView('pdf.payslip', [
            'payslip' => $payslip,
            'company' => $company,
            'qrDataUri' => $qrDataUri,
            'brand' => BrandColors::forCompany($company->primary_color),
        ]);

        return $pdf->download("payslip-{$payslip->payslip_number}.pdf");
    }

    private function authorizeAccess(Payslip $payslip): void
    {
        $ownEmployee = Employee::query()->where('user_id', Auth::id())->first();

        abort_if(
            $payslip->employee_id !== $ownEmployee?->id && ! Auth::user()->can('hr.payslips.view.all'),
            403,
        );
    }
}
