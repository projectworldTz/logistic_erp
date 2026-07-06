<?php

namespace App\Http\Resources;

use App\Support\Rbac\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\PermissionRegistrar;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(
            $this->tenant_id ?? PermissionRegistry::PLATFORM_TEAM_ID
        );

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'customer_id' => $this->customer_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_super_admin' => $this->is_super_admin,
            'status' => $this->status,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
        ];
    }
}
