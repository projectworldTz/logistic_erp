<?php

namespace App\Observers;

use App\Models\WarehouseItem;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class WarehouseItemObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(WarehouseItem $warehouseItem): void
    {
        $warehouseItem->reference_no = 'WH-'.now()->format('Y').'-'.str_pad((string) $warehouseItem->id, 5, '0', STR_PAD_LEFT);
        $warehouseItem->saveQuietly();

        $this->auditLogger->log(
            action: 'warehouse_item.created',
            auditable: $warehouseItem,
            newValues: $warehouseItem->only(['reference_no', 'customer_id', 'description', 'status']),
            tenantId: $warehouseItem->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'warehouse.items.view', 'warehouse_item.created', 'New warehouse item',
            "Warehouse item {$warehouseItem->reference_no} was received.",
            $warehouseItem, Auth::id(),
        );
    }

    public function updated(WarehouseItem $warehouseItem): void
    {
        $this->auditLogger->log(
            action: 'warehouse_item.updated',
            auditable: $warehouseItem,
            oldValues: $warehouseItem->getOriginal(),
            newValues: $warehouseItem->getChanges(),
            tenantId: $warehouseItem->tenant_id,
        );
    }

    public function deleted(WarehouseItem $warehouseItem): void
    {
        $this->auditLogger->log(
            action: 'warehouse_item.deleted',
            auditable: $warehouseItem,
            oldValues: $warehouseItem->only(['reference_no', 'customer_id']),
            tenantId: $warehouseItem->tenant_id,
        );
    }
}
