<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Shipments\SlaAlertService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class CheckShipmentSlaCommand extends Command
{
    protected $signature = 'sla:check-shipments';

    protected $description = 'Scan every tenant\'s in-flight shipments for delayed / near-deadline SLA breaches and notify once per breach';

    public function handle(SlaAlertService $service): int
    {
        $context = app(TenantContext::class);
        $totalDelayed = 0;
        $totalNearDeadline = 0;

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $context->set($tenantId);

            $result = $service->checkAndNotify();
            $totalDelayed += $result['delayed_alerted'];
            $totalNearDeadline += $result['near_deadline_alerted'];

            $context->clear();
        }

        $this->info("SLA check complete: {$totalDelayed} delayed alerts, {$totalNearDeadline} near-deadline alerts sent.");

        return self::SUCCESS;
    }
}
