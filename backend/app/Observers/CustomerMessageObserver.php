<?php

namespace App\Observers;

use App\Models\CustomerMessage;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class CustomerMessageObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(CustomerMessage $message): void
    {
        $this->auditLogger->log(
            action: 'customer_message.created',
            auditable: $message,
            newValues: $message->only(['customer_id', 'is_from_customer']),
            tenantId: $message->tenant_id,
        );

        if ($message->is_from_customer) {
            // Notify staff who manage this customer that a new portal message arrived.
            $this->notifications->notifyModuleUsers(
                'crm.customers.manage', 'customer_message.created', 'New client message',
                'A client sent a new message via the portal.',
                $message, Auth::id(),
            );

            return;
        }

        // Staff replied — notify only the portal user(s) for this specific
        // customer (not every portal user in the tenant), so this can't
        // reuse notifyModuleUsers' permission-based fan-out.
        $recipientIds = User::query()
            ->where('tenant_id', $message->tenant_id)
            ->where('customer_id', $message->customer_id)
            ->where('id', '!=', Auth::id())
            ->pluck('id');

        if ($recipientIds->isEmpty()) {
            return;
        }

        $now = now();

        \App\Models\UserNotification::query()->insert($recipientIds->map(fn ($id) => [
            'tenant_id' => $message->tenant_id,
            'user_id' => $id,
            'actor_id' => Auth::id(),
            'type' => 'customer_message.created',
            'notifiable_type' => $message->getMorphClass(),
            'notifiable_id' => $message->getKey(),
            'title' => 'New message',
            'message' => 'You have a new message from your logistics provider.',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }
}
