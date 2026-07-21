<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Candidate;
use App\Models\DisciplinaryRecord;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeDocument;
use App\Models\EmployeeLoan;
use App\Models\ExitRecord;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class HrDashboardController extends Controller
{
    /**
     * Real HR + payroll KPIs computed from the tables every module in
     * this phase already writes to — no separate summary/cache table,
     * same convention as AnalyticsController.
     */
    public function index(TenantContext $tenantContext)
    {
        $tenantId = $tenantContext->id();

        return response()->json([
            'headcount' => $this->headcount($tenantId),
            'attendance' => $this->attendanceToday($tenantId),
            'leave' => $this->leave($tenantId),
            'expiring' => $this->expiring($tenantId),
            'payroll' => $this->payroll($tenantId),
            'loans' => $this->loans($tenantId),
            'recruitment' => $this->recruitment($tenantId),
            'exits' => $this->exits($tenantId),
        ]);
    }

    private function headcount(int $tenantId): array
    {
        $total = Employee::query()->where('tenant_id', $tenantId)->where('status', '!=', 'terminated')->count();

        $byDepartment = Employee::query()
            ->where('employees.tenant_id', $tenantId)
            ->where('employees.status', '!=', 'terminated')
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            ->selectRaw('departments.name as department, count(*) as total')
            ->groupBy('departments.name')
            ->pluck('total', 'department');

        $byStatus = Employee::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => $total,
            'by_department' => $byDepartment,
            'by_status' => $byStatus,
        ];
    }

    private function attendanceToday(int $tenantId): array
    {
        $today = now()->toDateString();

        $byStatus = AttendanceRecord::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('date', $today)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return ['today' => $byStatus];
    }

    private function leave(int $tenantId): array
    {
        return [
            'pending_requests' => LeaveRequest::query()->where('tenant_id', $tenantId)->where('status', 'pending')->count(),
        ];
    }

    private function expiring(int $tenantId): array
    {
        $window = now()->addDays(30);

        return [
            'contracts' => EmployeeContract::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', $window)
                ->count(),
            'documents' => EmployeeDocument::query()
                ->where('tenant_id', $tenantId)
                ->where('status', '!=', 'rejected')
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', $window)
                ->count(),
        ];
    }

    private function payroll(int $tenantId): array
    {
        $lastFinalized = PayrollRun::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'finalized')
            ->with('period')
            ->latest('finalized_at')
            ->first();

        $trend = PayrollRun::query()
            ->where('payroll_runs.tenant_id', $tenantId)
            ->where('payroll_runs.status', 'finalized')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_runs.payroll_period_id')
            ->where('payroll_periods.period_end', '>=', now()->subMonths(6)->startOfMonth())
            ->orderBy('payroll_periods.period_end')
            ->get(['payroll_periods.name as period_name', 'payroll_runs.total_net', 'payroll_runs.total_employer_cost']);

        return [
            'last_run' => $lastFinalized ? [
                'period_name' => $lastFinalized->period?->name,
                'total_gross' => $lastFinalized->total_gross,
                'total_deductions' => $lastFinalized->total_deductions,
                'total_net' => $lastFinalized->total_net,
                'total_employer_cost' => $lastFinalized->total_employer_cost,
            ] : null,
            'pending_approval_runs' => PayrollRun::query()->where('tenant_id', $tenantId)->where('status', 'pending_approval')->count(),
            'trend' => $trend,
        ];
    }

    private function loans(int $tenantId): array
    {
        return [
            'pending_loans' => EmployeeLoan::query()->where('tenant_id', $tenantId)->where('status', 'pending_approval')->count(),
            'pending_advances' => SalaryAdvance::query()->where('tenant_id', $tenantId)->where('status', 'pending_approval')->count(),
            'outstanding_loan_balance' => (string) DB::table('loan_schedules')
                ->where('tenant_id', $tenantId)->where('status', 'pending')->sum('amount'),
        ];
    }

    private function recruitment(int $tenantId): array
    {
        return [
            'open_vacancies' => JobVacancy::query()->where('tenant_id', $tenantId)->where('status', 'open')->count(),
            'candidates_in_pipeline' => JobApplication::query()
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['hired', 'rejected', 'withdrawn'])
                ->count(),
            'total_candidates' => Candidate::query()->where('tenant_id', $tenantId)->count(),
        ];
    }

    private function exits(int $tenantId): array
    {
        return [
            'in_progress' => ExitRecord::query()->where('tenant_id', $tenantId)->whereIn('status', ['initiated', 'in_progress', 'cleared'])->count(),
            'open_disciplinary' => DisciplinaryRecord::query()->where('tenant_id', $tenantId)->whereNotIn('status', ['resolved'])->count(),
        ];
    }
}
