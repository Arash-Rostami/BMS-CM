<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Master\PermissionResource\Pages\ManagePermissions;
use App\Filament\Resources\Master\PermissionResource\Traits\Filters as PermissionFilters;
use App\Filament\Resources\Master\PermissionResource\Traits\Form as PermissionForm;
use App\Filament\Resources\Master\PermissionResource\Traits\Infolist as PermissionInfolist;
use App\Filament\Resources\Master\PermissionResource\Traits\Table as PermissionTable;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\Permission;
use App\Services\PermissionLabeler;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PermissionResource extends Resource
{
    use HasResourcePermissions, PermissionFilters, PermissionForm, PermissionInfolist, PermissionTable;

    protected static ?string $model = Permission::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-key';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::getName(),
                        static::getRoles(),
                        static::getUsers(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['roles', 'users']);
    }

    protected static function deleteWarning(int $roles, int $users): string
    {
        return __('resources/permission/strings.actions.delete_warning', ['roles' => $roles, 'users' => $users]);
    }

    public static function getModelLabel(): string
    {
        return __('resources/permission/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.base');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePermissions::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/permission/strings.general.plural_model_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::viewName(),
                        static::viewRoles(),
                        static::viewUsers(),
                        static::viewCreatedAt(),
                        static::viewUpdatedAt(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return TableComponents::emptyState($table
            ->columns([
                static::showName(),
                static::showRolesCount(),
                static::showUsersCount(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([static::getModuleFilter(), static::getUngrantedFilter()])
            ->filtersFormColumns(1)
            ->groups([
                Group::make('name')
                    ->label(__('resources/permission/strings.grouping.module'))
                    ->getKeyFromRecordUsing(fn (Permission $record) => Str::before($record->name, '.'))
                    ->getTitleFromRecordUsing(function (Permission $record) {
                        $module = Str::before($record->name, '.');
                        $options = PermissionLabeler::getModuleOptions();

                        return $options[$module] ?? Str::title(str_replace('_', ' ', $module));
                    })
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('name', $direction)),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->mountUsing(fn (Permission $record) => $record->loadMissing('users:id,name')),
                    EditAction::make(),
                    DeleteAction::make()->modalDescription(fn (Permission $record) => static::deleteWarning($record->roles_count, $record->users_count)),
                ]),
            ])
            ->toolbarActions([BulkActionGroup::make([static::getExportBulkAction(), DeleteBulkAction::make()->modalDescription(fn (Collection $records) => static::deleteWarning($records->sum('roles_count'), $records->sum('users_count')))])])
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc'));
    }
}
