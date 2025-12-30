<?php

namespace App\Filament\Resources\Master\RoleResource\Traits;

use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionLabeler;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

trait Form
{
    public static function getRoleNameInput(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/role/strings.form.name'))
            ->required()
            ->unique(ignoreRecord: true)
            ->maxLength(255)
            ->rules(['regex:/^[a-zA-Z_]+$/'])
            ->live(onBlur: true)
            ->afterStateHydrated(function (Set $set, ?string $state) {
                if (!$state) return;

                if ($grade = Role::extractGrade($state)) {
                    $set('name', Role::extractBaseName($state));
                    $set('grade', $grade);
                }
            })
            ->afterStateUpdated(fn(Set $set, $state) => $set('name', Str::snake($state)));
    }

    public static function getGradeSelect(): Select
    {
        return Select::make('grade')
            ->label(__('resources/role/strings.form.grade'))
            ->options(['junior' => '⭐', 'mid' => '⭐⭐', 'senior' => '⭐⭐⭐'])
            ->afterStateUpdated(function (Set $set, Get $get, ?string $grade) {
                $base = Role::extractBaseName($get('name') ?? '');
                $set('name', Role::combineName($base, $grade));
            })
            ->required();
    }

    public static function getSelectAllToggle(): Toggle
    {
        return Toggle::make('select_all')
            ->label(__('resources/role/strings.form.select_all'))
            ->onIcon('heroicon-s-check-circle')
            ->offIcon('heroicon-s-x-circle')
            ->live()
            ->afterStateUpdated(function (Set $set, bool $state) {
                if (!$state) {
                    $set('modules', []);
                    $set('permissions', []);
                    return;
                }

                $set('modules', array_keys(PermissionLabeler::getModuleOptions()));
                $set('permissions', Role::getAllPermissionIds());
            });
    }


    public static function getModuleSelector(): Select
    {
        return Select::make('modules')
            ->label(__('resources/role/strings.form.modules'))
            ->options(PermissionLabeler::getModuleOptions())
            ->multiple()
            ->live()
            ->searchable()
            ->columnSpanFull()
            ->afterStateHydrated(function (Set $set, $record) {
                if (!$record) return;

                $permissionIds = $record->permissions()->pluck('permissions.id')->all();
                $set('modules', Role::getModulesFromPermissions($permissionIds));
                $set('permissions', $permissionIds);
            })
            ->afterStateUpdated(function (Set $set, Get $get, ?array $state) {
                $state ??= [];
                $current = $get('permissions') ?? [];
                $previous = collect($get('modules') ?? []);
                $new = collect($state);

                $added = $new->diff($previous);
                $removed = $previous->diff($new);

                if ($added->isNotEmpty()) {
                    $current = array_unique(array_merge(
                        $current,
                        Role::getPermissionsForModules($added->all())
                    ));
                }

                if ($removed->isNotEmpty()) {
                    $current = array_diff(
                        $current,
                        Role::getPermissionsForModules($removed->all())
                    );
                }

                $set('permissions', array_values($current));
                $set('select_all', false);
            });
    }


    public static function getPermissionSelector(): Select
    {
        return Select::make('permissions')
            ->label(__('resources/role/strings.form.permissions'))
            ->columnSpanFull()
            ->options(function (Get $get) {
                $modules = $get('modules');
                $selectAll = $get('select_all');

                if (empty($modules) && !$selectAll) return [];

                $query = Permission::query();

                if (!empty($modules) && !$selectAll) {
                    $query->where(function ($q) use ($modules) {
                        foreach ($modules as $module) {
                            $q->orWhere('name', 'like', "{$module}.%");
                        }
                    });
                }

                return $query->get()
                    ->mapWithKeys(fn($p) => [$p->id => PermissionLabeler::getLabel($p->name)])
                    ->toArray();
            })
            ->multiple()
            ->live()
            ->searchable()
            ->disabled(fn(Get $get) => empty($get('modules')) && !$get('select_all'))
            ->afterStateUpdated(function (Set $set, Get $get, ?array $state) {
                $state ??= [];

                $set('modules', Role::getModulesFromPermissions($state));

                if (count($state) < Permission::count()) {
                    $set('select_all', false);
                }
            });
    }
}
