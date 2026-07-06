<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Enums\ShipmentDirection;
use App\Enums\TrackingEventType;
use App\Http\Controllers\Controller;
use App\Models\ClearingFile;
use App\Models\Container;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Real operational + financial KPIs, computed from data every other
     * module already stores (tracking events, clearance/dwell dates,
     * invoices, quotations) — not a new source of truth, an analysis of
     * the existing one. Defaults to the last 90 days.
     */
    public function overview(Request $request)
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : now()->subDays(90);
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : now();

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'operational' => $this->operational(),
            'financial' => $this->financial(),
            'trends' => $this->trends($from, $to),
            'top_customers' => $this->topCustomers(),
        ]);
    }

    private function operational(): array
    {
        $shipments = Shipment::query()->with('milestones')->get();

        $transitDaysByMode = [];
        $delivered = 0;
        $onTime = 0;

        foreach ($shipments as $shipment) {
            $departed = $shipment->milestones->firstWhere('event_type', TrackingEventType::Departed);
            $arrived = $shipment->milestones->firstWhere('event_type', TrackingEventType::Arrived);

            if ($departed && $arrived && $arrived->occurred_at->gt($departed->occurred_at)) {
                $transitDaysByMode[$shipment->mode->value][] = $departed->occurred_at->diffInDays($arrived->occurred_at);
            }

            $deliveredEvent = $shipment->milestones->firstWhere('event_type', TrackingEventType::Delivered);

            if ($deliveredEvent) {
                $delivered++;

                if ($shipment->eta && $deliveredEvent->occurred_at->lte($shipment->eta->endOfDay())) {
                    $onTime++;
                }
            }
        }

        $avgTransitDaysByMode = collect($transitDaysByMode)
            ->map(fn ($days) => round(array_sum($days) / count($days), 1));

        $clearanceDurations = ClearingFile::query()
            ->whereNotNull('cleared_date')
            ->get(['created_at', 'cleared_date'])
            ->map(fn ($file) => $file->created_at->diffInDays($file->cleared_date));

        $dwellDurations = Container::query()
            ->whereNotNull('gate_in_date')
            ->whereNotNull('gate_out_date')
            ->get(['gate_in_date', 'gate_out_date'])
            ->map(fn ($container) => $container->gate_in_date->diffInDays($container->gate_out_date));

        $totalVehicles = Vehicle::query()->count();
        $activeVehicles = Vehicle::query()->where('status', 'active')->count();

        return [
            'avg_transit_days_by_mode' => $avgTransitDaysByMode,
            'avg_customs_clearance_days' => $clearanceDurations->isNotEmpty() ? round($clearanceDurations->avg(), 1) : null,
            'on_time_delivery_rate' => $delivered > 0 ? round($onTime / $delivered * 100, 1) : null,
            'avg_container_dwell_days' => $dwellDurations->isNotEmpty() ? round($dwellDurations->avg(), 1) : null,
            'fleet_utilization_percent' => $totalVehicles > 0 ? round($activeVehicles / $totalVehicles * 100, 1) : null,
        ];
    }

    private function financial(): array
    {
        $revenueByMonth = Invoice::query()
            ->where('status', 'paid')
            ->get(['issue_date', 'total_amount'])
            ->groupBy(fn ($invoice) => $invoice->issue_date->format('Y-m'))
            ->map(fn ($group) => (float) $group->sum('total_amount'))
            ->sortKeys();

        $agingBuckets = ['current' => 0.0, 'days_1_30' => 0.0, 'days_31_60' => 0.0, 'days_61_90' => 0.0, 'days_over_90' => 0.0];

        foreach (Invoice::query()->whereIn('status', ['sent', 'overdue'])->get(['due_date', 'total_amount']) as $invoice) {
            $amount = (float) $invoice->total_amount;

            if (! $invoice->due_date->isPast()) {
                $agingBuckets['current'] += $amount;

                continue;
            }

            $daysOverdue = $invoice->due_date->diffInDays(now());

            $bucket = match (true) {
                $daysOverdue <= 30 => 'days_1_30',
                $daysOverdue <= 60 => 'days_31_60',
                $daysOverdue <= 90 => 'days_61_90',
                default => 'days_over_90',
            };

            $agingBuckets[$bucket] += $amount;
        }

        $margins = Invoice::query()
            ->whereNotNull('shipment_id')
            ->with('shipment.quotation')
            ->get()
            ->map(fn ($invoice) => [
                'shipment_number' => $invoice->shipment?->shipment_number,
                'quoted_amount' => $invoice->shipment?->quotation?->total_amount !== null
                    ? (float) $invoice->shipment->quotation->total_amount : null,
                'invoiced_amount' => (float) $invoice->total_amount,
            ])
            ->filter(fn ($row) => $row['quoted_amount'] !== null)
            ->map(fn ($row) => [...$row, 'variance' => round($row['invoiced_amount'] - $row['quoted_amount'], 2)])
            ->values();

        return [
            'revenue_by_month' => $revenueByMonth,
            'ar_aging' => $agingBuckets,
            'margins' => $margins,
        ];
    }

    private function trends(Carbon $from, Carbon $to): array
    {
        $volumeByMonth = Shipment::query()
            ->whereBetween('created_at', [$from, $to])
            ->get(['created_at', 'direction'])
            ->groupBy(fn ($shipment) => $shipment->created_at->format('Y-m'))
            ->map(fn ($group) => [
                'total' => $group->count(),
                'import' => $group->where('direction', ShipmentDirection::Import)->count(),
                'export' => $group->where('direction', ShipmentDirection::Export)->count(),
            ])
            ->sortKeys();

        return ['shipment_volume_by_month' => $volumeByMonth];
    }

    private function topCustomers(): array
    {
        $byRevenue = Invoice::query()
            ->where('status', 'paid')
            ->selectRaw('customer_id, SUM(total_amount) as revenue')
            ->groupBy('customer_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->with('customer:id,company_name')
            ->get()
            ->map(fn ($row) => ['customer' => $row->customer?->company_name, 'revenue' => (float) $row->revenue]);

        $byVolume = Shipment::query()
            ->selectRaw('customer_id, COUNT(*) as shipment_count')
            ->groupBy('customer_id')
            ->orderByDesc('shipment_count')
            ->limit(5)
            ->with('customer:id,company_name')
            ->get()
            ->map(fn ($row) => ['customer' => $row->customer?->company_name, 'shipment_count' => $row->shipment_count]);

        return ['by_revenue' => $byRevenue, 'by_volume' => $byVolume];
    }
}
