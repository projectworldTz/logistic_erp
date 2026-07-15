<?php

namespace App\Services\Shipments;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Services\Notifications\NotificationService;

class SlaAlertService
{
    private const NOTIFY_PERMISSION = 'shipments.items.view';

    private const NEAR_DEADLINE_WINDOW_HOURS = 48;

    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Scan this tenant's in-flight shipments for two SLA breaches — already
     * past ETA (delayed) and approaching it within the alert window
     * (near-deadline) — and notify once per shipment per breach type. The
     * *_alert_sent_at timestamps are the de-duplication guard: a shipment
     * that stays delayed across many scheduler runs is only ever announced
     * once, not every run.
     */
    public function checkAndNotify(): array
    {
        $completedStatuses = [ShipmentStatus::Arrived, ShipmentStatus::Delivered, ShipmentStatus::Cancelled];

        $delayed = Shipment::query()
            ->whereNotNull('eta')
            ->where('eta', '<', now()->startOfDay())
            ->whereNull('delayed_alert_sent_at')
            ->whereNotIn('status', $completedStatuses)
            ->with('customer')
            ->get();

        foreach ($delayed as $shipment) {
            $this->notifications->notifyModuleUsers(
                self::NOTIFY_PERMISSION,
                'shipment.delayed',
                'Shipment delayed',
                "Shipment {$shipment->shipment_number} for {$shipment->customer?->company_name} is past its ETA ({$shipment->eta->toDateString()}) and has not arrived.",
                $shipment,
            );
            $shipment->forceFill(['delayed_alert_sent_at' => now()])->save();
        }

        $nearDeadline = Shipment::query()
            ->whereNotNull('eta')
            ->whereBetween('eta', [now()->startOfDay(), now()->addHours(self::NEAR_DEADLINE_WINDOW_HOURS)])
            ->whereNull('near_deadline_alert_sent_at')
            ->whereNotIn('status', $completedStatuses)
            ->with('customer')
            ->get();

        foreach ($nearDeadline as $shipment) {
            $this->notifications->notifyModuleUsers(
                self::NOTIFY_PERMISSION,
                'shipment.near_deadline',
                'Shipment approaching ETA',
                "Shipment {$shipment->shipment_number} for {$shipment->customer?->company_name} is due by {$shipment->eta->toDateString()}.",
                $shipment,
            );
            $shipment->forceFill(['near_deadline_alert_sent_at' => now()])->save();
        }

        return [
            'delayed_alerted' => $delayed->count(),
            'near_deadline_alerted' => $nearDeadline->count(),
        ];
    }
}
