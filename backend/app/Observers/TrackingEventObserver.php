<?php

namespace App\Observers;

use App\Models\TrackingEvent;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class TrackingEventObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(TrackingEvent $trackingEvent): void
    {
        $this->auditLogger->log(
            action: 'tracking_event.created',
            auditable: $trackingEvent,
            newValues: $trackingEvent->only(['trackable_type', 'trackable_id', 'event_type', 'location']),
            tenantId: $trackingEvent->tenant_id,
        );

        if ($trackingEvent->trackable_type === \App\Models\Shipment::class) {
            $shipment = $trackingEvent->trackable;

            $this->notifications->notifyModuleUsers(
                'shipments.items.view', 'tracking_event.created', 'Shipment milestone added',
                "Shipment {$shipment?->shipment_number} reached '{$trackingEvent->event_type->value}'.",
                $trackingEvent, Auth::id(),
            );
        }
    }
}
