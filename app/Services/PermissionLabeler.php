<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Support\Str;

class PermissionLabeler
{
    public static function getLabel(string $permissionName): string
    {
        [$module, $action] = self::parsePermissionName($permissionName);

        if ($action === '') return self::prettifyModuleName($module);

        $actionLabel = self::getActionLabel($action);
        $moduleLabel = self::getModuleLabel($module);

        $moduleKey = self::moduleTranslationKey($module);
        $moduleKeyString = "resources/{$moduleKey}/strings.general.model_label";

        return $moduleLabel === $moduleKeyString
            ? "{$actionLabel} " . self::prettifyModuleName($module)
            : "{$actionLabel} {$moduleLabel}";
    }

    public static function getModuleOptions(): array
    {
        return Permission::pluck('name')
            ->map(fn($p) => Str::before($p, '.'))
            ->unique()
            ->mapWithKeys(function ($module) {
                $label = self::getModuleLabel($module);

                return [
                    $module => $label === self::moduleTranslationKeyString($module)
                        ? self::prettifyModuleName($module)
                        : $label
                ];
            })
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->all();
    }

    private static function parsePermissionName(string $permissionName): array
    {
        return array_pad(explode('.', $permissionName, 2), 2, '');
    }

    private static function prettifyModuleName(string $module): string
    {
        return Str::title(str_replace('_', ' ', $module));
    }

    private static function moduleTranslationKeyString(string $module): string
    {
        return "resources/" . self::moduleTranslationKey($module) . "/strings.general.model_label";
    }

    private static function moduleTranslationKey(string $module): string
    {
        return Str::snake(Str::singular(str_replace('_', '', $module)), '-');
    }

    private static function getModuleLabel(string $module): string
    {
        return __("resources/" . self::moduleTranslationKey($module) . "/strings.general.model_label");
    }

    private static function getActionLabel(string $action): string
    {
        return __('resources/general/strings.actions.' . $action);
    }
}
