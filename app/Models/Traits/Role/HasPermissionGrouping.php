<?php

namespace App\Models\Traits\Role;

use App\Models\Permission;
use Illuminate\Support\Collection;

trait HasPermissionGrouping
{
    public static function getModulesFromPermissions(array $permissionIds): array
    {
        return Permission::whereIn('id', $permissionIds)
            ->pluck('name')
            ->map(fn($name) => strstr($name, '.', true))
            ->unique()
            ->values()
            ->all();
    }

    public static function getPermissionsForModules(array $modules): array
    {
        return Permission::where(function ($q) use ($modules) {
            foreach ($modules as $module) {
                $q->orWhere('name', 'like', "{$module}.%");
            }
        })->pluck('id')->all();
    }

    public static function getAllPermissionIds(): array
    {
        return Permission::pluck('id')->all();
    }

    public function syncModules(array $modules): self
    {
        $permissions = self::getPermissionsForModules($modules);
        $this->syncPermissions($permissions);
        return $this;
    }

    public function getModulesAttribute(): array
    {
        return self::getModulesFromPermissions(
            $this->permissions()->pluck('permissions.id')->all()
        );
    }
}
