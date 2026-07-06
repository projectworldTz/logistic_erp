<?php

namespace App\Observers;

use App\Models\FreightBooking;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class FreightBookingObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(FreightBooking $freightBooking): void
    {
        $freightBooking->reference_no = 'FWD-'.now()->format('Y').'-'.str_pad((string) $freightBooking->id, 5, '0', STR_PAD_LEFT);
        $freightBooking->saveQuietly();

        $this->auditLogger->log(
            action: 'freight_booking.created',
            auditable: $freightBooking,
            newValues: $freightBooking->only(['reference_no', 'customer_id', 'direction', 'status']),
            tenantId: $freightBooking->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'freight.bookings.view', 'freight_booking.created', 'New freight booking',
            "Freight booking {$freightBooking->reference_no} was created.",
            $freightBooking, Auth::id(),
        );
    }

    public function updated(FreightBooking $freightBooking): void
    {
        $this->auditLogger->log(
            action: 'freight_booking.updated',
            auditable: $freightBooking,
            oldValues: $freightBooking->getOriginal(),
            newValues: $freightBooking->getChanges(),
            tenantId: $freightBooking->tenant_id,
        );
    }

    public function deleted(FreightBooking $freightBooking): void
    {
        $this->auditLogger->log(
            action: 'freight_booking.deleted',
            auditable: $freightBooking,
            oldValues: $freightBooking->only(['reference_no', 'customer_id']),
            tenantId: $freightBooking->tenant_id,
        );
    }
}
