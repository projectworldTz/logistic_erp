<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\UserNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

class NotificationService
{
    /**
     * Notify every tenant user with the given view permission that a new
     * record was created, excluding the user who created it.
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

        $recipientIds = User::query()->permission($permission)
            ->where('tenant_id', $tenantId)
            ->when($actorId, fn ($query) => $query->where('id', '!=', $actorId))
            ->pluck('id');

        if ($recipientIds->isEmpty()) {
            return;
        }

        $now = now();

        UserNotification::query()->insert($recipientIds->map(fn ($id) => [
            'tenant_id' => $tenantId,
            'user_id' => $id,
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
