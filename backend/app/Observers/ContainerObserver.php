<?php

namespace App\Observers;

use App\Models\Container;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class ContainerObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(Container $container): void
    {
        $this->auditLogger->log(
            action: 'container.created',
            auditable: $container,
            newValues: $container->only(['container_number', 'customer_id', 'container_type', 'status']),
            tenantId: $container->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'containers.items.view', 'container.created', 'New container',
            "Container {$container->container_number} was added.",
            $container, Auth::id(),
        );
    }

    public function updated(Container $container): void
    {
        $this->auditLogger->log(
            action: 'container.updated',
            auditable: $container,
            oldValues: $container->getOriginal(),
            newValues: $container->getChanges(),
            tenantId: $container->tenant_id,
        );
    }

    public function deleted(Container $container): void
    {
        $this->auditLogger->log(
            action: 'container.deleted',
            auditable: $container,
            oldValues: $container->only(['container_number', 'customer_id']),
            tenantId: $container->tenant_id,
        );
    }
}
