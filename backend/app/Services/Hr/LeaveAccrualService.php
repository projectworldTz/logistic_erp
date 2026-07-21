<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Support\Tenancy\TenantContext;

/**
 * Monthly leave accrual: each active leave type configured with
 * accrual_rule=monthly grants 1/12th of its default_annual_days to every
 * payroll-eligible employee's balance for the current year. Configurable
 * per leave type (not hard-coded to Tanzania's or any statute's annual
 * leave figure) — a tenant with no monthly-accrual leave types simply
 * accrues nothing, which is a valid configuration, not a bug.
 */
class LeaveAccrualService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function accrueForCurrentTenant(): int
    {
        $tenantId = $this->tenantContext->id();
        $year = now()->year;
        $accrued = 0;

        $leaveTypes = LeaveType::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('accrual_rule', 'monthly')
            ->whereNotNull('default_annual_days')
            ->get();

        if ($leaveTypes->isEmpty()) {
            return 0;
        }

        $employees = Employee::query()
            ->where('tenant_id', $tenantId)
            ->where('payroll_eligible', true)
            ->where('status', '!=', 'terminated')
            ->get();

        foreach ($leaveTypes as $leaveType) {
            $monthlyAccrual = round(((float) $leaveType->default_annual_days) / 12, 2);

            foreach ($employees as $employee) {
                $balance = LeaveBalance::query()->firstOrCreate(
                    ['tenant_id' => $tenantId, 'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => $year],
                    ['entitled_days' => 0, 'used_days' => 0, 'carried_forward_days' => 0],
                );

                $balance->increment('entitled_days', $monthlyAccrual);
                $accrued++;
            }
        }

        return $accrued;
    }
}
