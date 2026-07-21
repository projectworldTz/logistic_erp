<?php

namespace App\Services\Rbac;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Support\Rbac\PermissionRegistry;
use Illuminate\Support\Facades\DB;
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
     * Create a tenant's own copies of the 19 tenant-level roles, scoped to
     * that tenant via the teams feature, and grant every role's default
     * permissions in one pass.
     *
     * Deliberately bypasses Spatie's per-role syncPermissions()/
     * givePermissionTo() here: each of those calls forgetCachedPermissions()
     * internally, which forces the *next* role's permission lookup to
     * rebuild Spatie's full permission cache from every role/permission
     * pivot row in the database — for every one of the 19 roles. That cache
     * rebuild cost scales with total roles/permissions across ALL tenants,
     * so registration got dramatically slower as more tenants were
     * provisioned (seconds, then tens of seconds). Bulk-inserting the pivot
     * rows directly and forgetting the cache exactly once avoids the
     * (existing tenant count) × (roles per tenant) blowup entirely.
     */
    public function seedForTenant(int $tenantId): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        $tenantRoles = config('rbac.tenant_roles', []);
        $now = now();

        DB::table('roles')->insert(array_map(fn (string $roleName) => [
            'name' => $roleName,
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $tenantRoles));

        $roleIds = Role::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('name', $tenantRoles)
            ->pluck('id', 'name');

        $permissionIds = Permission::query()->pluck('id', 'name');

        $pivotRows = [];
        foreach ($tenantRoles as $roleName) {
            $expanded = PermissionRegistry::expand(config("rbac.default_role_permissions.{$roleName}", []));

            foreach ($expanded as $permissionName) {
                if (! isset($permissionIds[$permissionName])) {
                    continue;
                }

                $pivotRows[] = [
                    'permission_id' => $permissionIds[$permissionName],
                    'role_id' => $roleIds[$roleName],
                ];
            }
        }

        if (! empty($pivotRows)) {
            DB::table('role_has_permissions')->insert($pivotRows);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Create any tenant_roles entries added to config/rbac.php *after* a
     * tenant was first provisioned (e.g. a new "HR Manager" role) as an
     * actual Role row for every existing tenant. seedForTenant() only runs
     * at registration time, so without this, a role added later would only
     * ever exist for brand-new tenants — existing tenants would have no
     * such role to assign at all. Only inserts the missing rows; existing
     * roles/assignments are untouched.
     */
    public function backfillMissingTenantRoles(): void
    {
        $tenantRoles = config('rbac.tenant_roles', []);
        $now = now();

        Tenant::query()->pluck('id')->each(function (int $tenantId) use ($tenantRoles, $now) {
            $existing = Role::query()->where('tenant_id', $tenantId)->whereIn('name', $tenantRoles)->pluck('name')->all();
            $missing = array_values(array_diff($tenantRoles, $existing));

            if (empty($missing)) {
                return;
            }

            DB::table('roles')->insert(array_map(fn (string $roleName) => [
                'name' => $roleName,
                'guard_name' => 'web',
                'tenant_id' => $tenantId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $missing));
        });
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
        $this->backfillMissingTenantRoles();

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
