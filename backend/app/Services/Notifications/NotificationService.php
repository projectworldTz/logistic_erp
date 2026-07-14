<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\UserNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
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

        foreach ($recipients as $recipient) {
            $this->externalDispatcher->dispatch($recipient, $title, $message);
        }
    }
}
