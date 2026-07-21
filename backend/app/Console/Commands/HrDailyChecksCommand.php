<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Hr\HrAlertService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class HrDailyChecksCommand extends Command
{
    protected $signature = 'hr:daily-checks';

    protected $description = 'Scan every tenant for contract/document expiries, missing attendance, due loan installments, and payroll periods approaching their payment date without a finalized run';

    public function handle(HrAlertService $service, TenantContext $context): int
    {
        $totals = [];

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $context->set($tenantId);

            foreach ($service->runDailyChecks() as $check => $count) {
                $totals[$check] = ($totals[$check] ?? 0) + $count;
            }

            $context->clear();
        }

        foreach ($totals as $check => $count) {
            $this->info("{$check}: {$count} alert(s) sent.");
        }

        return self::SUCCESS;
    }
}
