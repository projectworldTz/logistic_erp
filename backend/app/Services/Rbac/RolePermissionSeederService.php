<?php

namespace App\Services\Rbac;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Support\Rbac\PermissionRegistry;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeederService
{
    /**
     * Create (or update) every permission in the catalog. Permissions are
     * global, not team-scoped, so this only needs to run once.
     */
    public function seedPermissions(): void
    {
        foreach (config('rbac.permissions', []) as $module => $permissions) {
            foreach ($permissions as $name => $description) {
                Permission::query()->updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['module' => $module, 'description' => $description],
                );
            }
        }
    }

    /**
     * Create the global platform roles (tenant_id = null) with their
     * default permissions. Run once, at application bootstrap.
     */
    public function seedGlobalRoles(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(PermissionRegistry::PLATFORM_TEAM_ID);

        foreach (config('rbac.global_roles', []) as $roleName) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => PermissionRegistry::PLATFORM_TEAM_ID],
            );

            $this->syncRolePermissions($role, $roleName);
        }
    }

    /**
     * Create a tenant's own copies of the 19 tenant-level roles, scoped
     * to that tenant via the teams feature. Called during provisioning.
     */
    public function seedForTenant(int $tenantId): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        foreach (config('rbac.tenant_roles', []) as $roleName) {
            $role = Role::query()->create([
                'name' => $roleName,
                'guard_name' => 'web',
                'tenant_id' => $tenantId,
            ]);

            $this->syncRolePermissions($role, $roleName);
        }
    }

    /**
     * Re-sync every existing role's permissions (global + every tenant's)
     * from the current config/rbac.php. Safe to run repeatedly, and safe
     * against tenants that predate a newly added permission module: roles
     * not present in default_role_permissions are left untouched rather
     * than wiped, since syncRolePermissions() only calls syncPermissions()
     * when the expanded list is non-empty.
     */
    public function resyncAllExistingRoles(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(PermissionRegistry::PLATFORM_TEAM_ID);
        Role::query()->where('tenant_id', PermissionRegistry::PLATFORM_TEAM_ID)->get()
            ->each(fn (Role $role) => $this->syncRolePermissions($role, $role->name));

        Tenant::query()->pluck('id')->each(function (int $tenantId) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
            Role::query()->where('tenant_id', $tenantId)->get()
                ->each(fn (Role $role) => $this->syncRolePermissions($role, $role->name));
        });
    }

    private function syncRolePermissions(Role $role, string $roleName): void
    {
        $names = config("rbac.default_role_permissions.{$roleName}", []);
        $expanded = PermissionRegistry::expand($names);

        if (! empty($expanded)) {
            $role->syncPermissions($expanded);
        }
    }
}
