<?php

namespace App\Support\Rbac;

class PermissionRegistry
{
    /**
     * Spatie's teams pivot columns (model_has_roles.tenant_id etc.) are
     * NOT NULL, so global/platform roles can't use a real null team id.
     * 0 is reserved as the "platform" team instead.
     */
    public const PLATFORM_TEAM_ID = 0;

    /**
     * Flat map of permission name => description, across all modules.
     */
    public static function all(): array
    {
        $flat = [];

        foreach (config('rbac.permissions', []) as $module) {
            $flat += $module;
        }

        return $flat;
    }

    /**
     * Expand a role's configured permission list, resolving 'module.*'
     * wildcards against the full catalog.
     */
    public static function expand(array $permissionNames): array
    {
        $all = array_keys(self::all());
        $expanded = [];

        foreach ($permissionNames as $name) {
            if (str_ends_with($name, '.*')) {
                $prefix = substr($name, 0, -1);
                $expanded = array_merge(
                    $expanded,
                    array_filter($all, fn ($p) => str_starts_with($p, $prefix))
                );

                continue;
            }

            $expanded[] = $name;
        }

        return array_values(array_unique($expanded));
    }
}
