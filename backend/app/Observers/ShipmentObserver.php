<?php

namespace App\Observers;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ShipmentObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(Shipment $shipment): void
    {
        $shipment->shipment_number = 'SHP-'.now()->format('Y').'-'.str_pad((string) $shipment->id, 5, '0', STR_PAD_LEFT);
        // Globally unique (not tenant-scoped) so a public /track/{code} lookup
        // can find the right shipment without ambiguity — shipment_number is
        // only unique per tenant, so it can't serve that purpose alone.
        $shipment->tracking_code = 'TRK-'.str_pad((string) $shipment->id, 8, '0', STR_PAD_LEFT).strtoupper(Str::random(4));
        $shipment->saveQuietly();

        $this->auditLogger->log(
            action: 'shipment.created',
            auditable: $shipment,
            newValues: $shipment->only(['shipment_number', 'customer_id', 'direction', 'status']),
            tenantId: $shipment->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'shipments.items.view', 'shipment.created', 'New shipment',
            "Shipment {$shipment->shipment_number} was created.",
            $shipment, Auth::id(),
        );
    }

    public function updated(Shipment $shipment): void
    {
        $this->auditLogger->log(
            action: 'shipment.updated',
            auditable: $shipment,
            oldValues: $shipment->getOriginal(),
            newValues: $shipment->getChanges(),
            tenantId: $shipment->tenant_id,
        );

        if ($shipment->wasChanged('status') && $shipment->status === ShipmentStatus::Delivered && $shipment->customer) {
            $this->notifications->notifyCustomer(
                $shipment->customer,
                'shipment.delivered',
                'Shipment delivered',
                $this->deliveredMessage($shipment),
                $shipment,
            );
        }
    }

    /**
     * A message unique to this shipment/customer pair — not a generic
     * canned line — so the customer sees exactly which cargo arrived.
     */
    private function deliveredMessage(Shipment $shipment): string
    {
        $shipment->loadMissing(['customer', 'clearingFile', 'freightBooking']);

        $cargoDescription = $shipment->clearingFile?->cargo_description ?? $shipment->freightBooking?->cargo_description;
        $cargoSuffix = $cargoDescription ? " ({$cargoDescription})" : '';
        $trackingUrl = rtrim(config('app.frontend_url'), '/')."/track/{$shipment->tracking_code}";

        return "Hi {$shipment->customer->company_name}, good news — your shipment {$shipment->shipment_number}{$cargoSuffix} has been delivered. Track it any time at {$trackingUrl}.";
    }

    public function deleted(Shipment $shipment): void
    {
        $this->auditLogger->log(
            action: 'shipment.deleted',
            auditable: $shipment,
            oldValues: $shipment->only(['shipment_number', 'customer_id']),
            tenantId: $shipment->tenant_id,
        );
    }
}
