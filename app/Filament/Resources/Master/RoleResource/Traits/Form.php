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
    public static function getActionToggle(string $action): Toggle
    {
        return Toggle::make("action_{$action}")
            ->label(__("resources/general/strings.actions.{$action}"))
            ->live()
            ->afterStateHydrated(function (Toggle $component, $record) use ($action) {
                if (!$record) return;
                $total = Permission::where('name', 'like', "%.{$action}")->count();
                $component->state(
                    $total > 0 &&
                    $record->permissions()->where('name', 'like', "%.{$action}")->count() === $total
                );
            })
            ->afterStateUpdated(function (Set $set, Get $get, bool $state) use ($action) {
                $current = $get('permissions') ?? [];
                $actionPerms = Permission::where('name', 'like', "%.{$action}")->pluck('id')->all();

                $current = $state
                    ? array_values(array_unique(array_merge($current, $actionPerms)))
                    : array_values(array_diff($current, $actionPerms));

                $set('permissions', $current);
                $set('modules', Role::getModulesFromPermissions($current));
                $set('select_all', count($current) === Permission::count());
            });
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
            ->required()
            ->validationAttribute(__('resources/role/strings.form.grade'))
            ->helperText(__('resources/role/strings.form.helper_grade'))
            ->validationMessages([
                'required' => __('resources/role/strings.form.validation_grade_required'),
            ]);
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
            ->helperText(__('resources/role/strings.form.helper_modules'))
            ->afterStateHydrated(function (Set $set, $record) {
                if (!$record) return;
                $permissionIds = $record->permissions()->pluck('permissions.id')->all();
                $set('modules', Role::getModulesFromPermissions($permissionIds));
                $set('permissions', $permissionIds);
            })
            ->afterStateUpdated(function (Set $set, Get $get, ?array $state) {
                $state ??= [];
                $current = $get('permissions') ?? [];

                $previous = collect(Role::getModulesFromPermissions($current));
                $new = collect($state);

                $added = $new->diff($previous);
                $removed = $previous->diff($new);

                if ($added->isNotEmpty()) {
                    $current = array_unique(array_merge($current, Role::getPermissionsForModules($added->all())));
                }
                if ($removed->isNotEmpty()) {
                    $current = array_diff($current, Role::getPermissionsForModules($removed->all()));
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
                    ->groupBy(fn($p) => PermissionLabeler::getLabel(Str::before($p->name, '.')))
                    ->map(fn($perms) => $perms->mapWithKeys(fn($p) => [$p->id => PermissionLabeler::getLabel($p->name)])->toArray())
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

    public static function getRoleNameInput(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/role/strings.form.name'))
            ->required()
            ->unique(ignoreRecord: true)
            ->maxLength(255)
            ->rules(['regex:/^[a-zA-Z_]+$/'])
            ->helperText(__('resources/role/strings.form.helper_name'))
            ->validationAttribute(__('resources/role/strings.form.name'))
            ->validationMessages([
                'required' => __('resources/role/strings.form.validation_name_required'),
                'unique' => __('resources/role/strings.form.validation_name_unique'),
                'regex' => __('resources/role/strings.form.validation_name_regex'),
                'max' => __('resources/role/strings.form.validation_name_max'),
            ])
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
}
