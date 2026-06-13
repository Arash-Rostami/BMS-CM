<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Master\PermissionResource\Pages\ManagePermissions;
use App\Filament\Resources\Master\PermissionResource\Traits\Filters as PermissionFilters;
use App\Filament\Resources\Master\PermissionResource\Traits\Form as PermissionForm;
use App\Filament\Resources\Master\PermissionResource\Traits\Infolist as PermissionInfolist;
use App\Filament\Resources\Master\PermissionResource\Traits\Table as PermissionTable;
use App\Filament\Traits\HasResourcePermissions;
use App\Services\PermissionLabeler;
use App\Services\SmartCacheManager;
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
use App\Models\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PermissionResource extends Resource
{
    use PermissionForm, PermissionTable, PermissionInfolist, PermissionFilters, HasResourcePermissions;

    protected static ?string $model = Permission::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-key';

    protected static ?int $navigationSort = 10;

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

    public static function getModelLabel(): string
    {
        return __('resources/permission/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.base');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SmartCacheManager::remember(
            'Permission',
            ['type' => 'total_count'],
            3600,
            fn() => static::getModel()::count()
        );

        return $count > 0 ? (string)$count : null;
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
                        static::viewCreatedAt(),
                        static::viewUpdatedAt(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::showName(),
                static::showRolesCount(),
                static::showUsersCount(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([static::getModuleFilter()])
            ->filtersFormColumns(1)
            ->groups([
                Group::make('name')
                    ->label(__('resources/permission/strings.grouping.module'))
                    ->getKeyFromRecordUsing(fn(Permission $record) => Str::before($record->name, '.'))
                    ->getTitleFromRecordUsing(function (Permission $record) {
                        $module = Str::before($record->name, '.');
                        $options = PermissionLabeler::getModuleOptions();
                        return $options[$module] ?? Str::title(str_replace('_', ' ', $module));
                    })
                    ->orderQueryUsing(fn(Builder $query, string $direction) => $query->orderBy('name', $direction)),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc')
            ->defaultPaginationPageOption(25);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['roles', 'users']);
    }
}
