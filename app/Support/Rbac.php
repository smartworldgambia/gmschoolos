<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Syncs config/rbac.php into the permission tables. Idempotent: safe to run on every deploy.
 *
 * Roles are synced to exactly the configured permissions, so a deploy can never silently leave a
 * role with a stale (wider) permission set. Per-school custom roles are not part of this sync.
 */
class Rbac
{
    /** @return array<string, list<string>> role => permission names */
    public static function matrix(): array
    {
        $platform = config('rbac.permissions.platform', []);
        $school = config('rbac.permissions.school', []);

        $matrix = [];
        foreach (config('rbac.roles', []) as $role => $permissions) {
            $expanded = [];
            foreach ($permissions as $permission) {
                if ($permission === '*platform') {
                    $expanded = array_merge($expanded, $platform);
                } elseif ($permission === '*school') {
                    $expanded = array_merge($expanded, $school);
                } else {
                    $expanded[] = $permission;
                }
            }
            $matrix[$role] = array_values(array_unique($expanded));
        }

        return $matrix;
    }

    public static function sync(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = array_merge(config('rbac.permissions.platform', []), config('rbac.permissions.school', []));
        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::matrix() as $roleName => $permissions) {
            $unknown = array_diff($permissions, $all);
            if ($unknown) {
                throw new \InvalidArgumentException("Role {$roleName} references unknown permission(s): ".implode(', ', $unknown));
            }

            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<string> */
    public static function platformRoles(): array
    {
        return config('rbac.platform_roles', []);
    }
}
