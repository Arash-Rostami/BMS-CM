<?php

namespace App\Filament\Resources\Master\RoleResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionLabeler;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

trait Infolist
{
    public static function viewName(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/role/strings.infolist.name'))
            ->formatStateUsing(fn (string $state, Role $record) => $record->base_name)
            ->color(fn (string $state): string => UserRole::tryFrom($state)?->getColor() ?? 'gray')
            ->badge();
    }

    public static function viewGrade(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/role/strings.infolist.grade'))
            ->formatStateUsing(fn (string $state, Role $record) => $record->grade_label);
    }

    public static function viewPermissions(): Group
    {
        return Group::make([
            TextEntry::make('permissions_summary')
                ->label(__('resources/role/strings.infolist.permissions'))
                ->state(fn (Role $record) => __('resources/role/strings.infolist.permissions_summary', [
                    'count' => $record->loadMissing('permissions:id,name')->permissions->count(),
                    'total' => Permission::count(),
                ]))
                ->color('info')
                ->badge(),
            TextEntry::make('permissions_grouped')
                ->hiddenLabel()
                ->state(fn (Role $record) => static::groupedPermissionLines($record))
                ->listWithLineBreaks()
                ->badge(),
        ])->columnSpanFull();
    }

    public static function groupedPermissionLines(Role $record): array
    {
        $modules = PermissionLabeler::getModuleOptions();

        return $record->loadMissing('permissions:id,name')->permissions
            ->groupBy(fn ($permission) => Str::before($permission->name, '.'))
            ->map(fn ($group, $module) => ($modules[$module] ?? Str::headline($module)).': '.$group
                ->map(fn ($permission) => static::actionLabel(Str::after($permission->name, '.')))
                ->implode(__('resources/role/strings.infolist.separator')))
            ->sortKeys()
            ->values()
            ->all();
    }

    protected static function actionLabel(string $action): string
    {
        $key = 'resources/general/strings.actions.'.$action;

        return Lang::has($key) ? __($key) : Str::headline($action);
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/role/strings.infolist.created_at'))
            ->adaptiveDateTime('M Y | D: H:i:s')
            ->color('gray');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/role/strings.infolist.updated_at'))
            ->adaptiveDateTime('M Y | D: H:i:s')
            ->color('gray');
    }
}
