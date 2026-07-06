<?php

namespace App\Observers;

use App\Models\Vehicle;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class VehicleObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(Vehicle $vehicle): void
    {
        $this->auditLogger->log(
            action: 'vehicle.created',
            auditable: $vehicle,
            newValues: $vehicle->only(['registration_number', 'vehicle_type', 'status']),
            tenantId: $vehicle->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'fleet.vehicles.view', 'vehicle.created', 'New vehicle',
            "Vehicle {$vehicle->registration_number} was added.",
            $vehicle, Auth::id(),
        );
    }

    public function updated(Vehicle $vehicle): void
    {
        $this->auditLogger->log(
            action: 'vehicle.updated',
            auditable: $vehicle,
            oldValues: $vehicle->getOriginal(),
            newValues: $vehicle->getChanges(),
            tenantId: $vehicle->tenant_id,
        );
    }

    public function deleted(Vehicle $vehicle): void
    {
        $this->auditLogger->log(
            action: 'vehicle.deleted',
            auditable: $vehicle,
            oldValues: $vehicle->only(['registration_number']),
            tenantId: $vehicle->tenant_id,
        );
    }
}
