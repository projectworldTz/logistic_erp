<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Hr\LeaveAccrualService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class AccrueLeaveCommand extends Command
{
    protected $signature = 'hr:accrue-leave';

    protected $description = 'Grant monthly leave accrual (1/12th of each leave type\'s default annual days) to every payroll-eligible employee, for every tenant';

    public function handle(LeaveAccrualService $service, TenantContext $context): int
    {
        $total = 0;

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $context->set($tenantId);
            $total += $service->accrueForCurrentTenant();
            $context->clear();
        }

        $this->info("Leave accrual complete: {$total} balance(s) updated.");

        return self::SUCCESS;
    }
}
