<?php

namespace App\Services\Notifications;

use App\Models\Customer;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

class NotificationService
{
    public function __construct(private readonly ExternalNotificationDispatcher $externalDispatcher) {}

    /**
     * Notify every tenant user with the given view permission that a new
     * record was created, excluding the user who created it. Also fans the
     * same notification out to any external channels (email/SMS/WhatsApp)
     * the tenant has enabled.
     */
    public function notifyModuleUsers(
        string $permission,
        string $type,
        string $title,
        string $message,
        Model $notifiable,
        ?int $actorId = null,
    ): void {
        $tenantId = app(TenantContext::class)->id();

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        $recipients = User::query()->permission($permission)
            ->where('tenant_id', $tenantId)
            ->when($actorId, fn ($query) => $query->where('id', '!=', $actorId))
            ->get(['id', 'email', 'phone']);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->insertInAppNotifications($tenantId, $recipients, $type, $title, $message, $notifiable, $actorId);

        foreach ($recipients as $recipient) {
            $this->externalDispatcher->dispatch($recipient, $title, $message);
        }
    }

    /**
     * Notify a Customer that something about their own shipment/invoice/
     * quotation happened: an in-app notification for any portal User
     * accounts linked to that customer (customer_id), plus an email sent
     * directly to the customer's contact address regardless of whether a
     * portal account exists.
     */
    public function notifyCustomer(
        Customer $customer,
        string $type,
        string $title,
        string $message,
        Model $notifiable,
    ): void {
        $tenantId = app(TenantContext::class)->id();

        $portalUsers = User::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->get(['id']);

        if ($portalUsers->isNotEmpty()) {
            $this->insertInAppNotifications($tenantId, $portalUsers, $type, $title, $message, $notifiable);
        }

        $this->externalDispatcher->dispatchToCustomer($customer, $title, $message);
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function insertInAppNotifications(
        int $tenantId,
        Collection $recipients,
        string $type,
        string $title,
        string $message,
        Model $notifiable,
        ?int $actorId = null,
    ): void {
        $now = now();

        UserNotification::query()->insert($recipients->map(fn (User $recipient) => [
            'tenant_id' => $tenantId,
            'user_id' => $recipient->id,
            'actor_id' => $actorId,
            'type' => $type,
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'title' => $title,
            'message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }
}
