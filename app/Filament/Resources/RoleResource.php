<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Master\RoleResource\Pages\ManageRoles;
use App\Filament\Resources\Master\RoleResource\Traits\Filters as RoleFilters;
use App\Filament\Resources\Master\RoleResource\Traits\Form as RoleForm;
use App\Filament\Resources\Master\RoleResource\Traits\Infolist as RoleInfolist;
use App\Filament\Resources\Master\RoleResource\Traits\Table as RoleTable;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\Role;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoleResource extends Resource
{
    use HasResourcePermissions, RoleFilters, RoleForm, RoleInfolist, RoleTable;

    protected static ?string $model = Role::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-finger-print';

    protected static ?int $navigationSort = 9;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Grid::make(3)->schema([
                    static::getRoleNameInput(),
                    static::getGradeSelect(),
                    static::getSelectAllToggle(),
                ]),
                Grid::make(4)->schema([
                    static::getActionToggle('view'),
                    static::getActionToggle('create'),
                    static::getActionToggle('edit'),
                    static::getActionToggle('delete'),
                ]),
                static::getModuleSelector(),
                static::getPermissionSelector(),
            ])->columnSpanFull(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['permissions', 'users']);
    }

    public static function getModelLabel(): string
    {
        return __('resources/role/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.base');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRoles::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/role/strings.general.plural_model_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::viewName(),
                        static::viewGrade(),
                        static::viewPermissions(),
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
                static::showPermissionsCount(),
                static::showUsersCount(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getNameFilter(),
                static::getGradeFilter(),
            ])
            ->filtersFormColumns(2)
            ->groups([
                Group::make('name')
                    ->label(__('resources/role/strings.filters.group_base_name'))
                    ->getKeyFromRecordUsing(fn (Role $record) => $record->base_name)
                    ->getTitleFromRecordUsing(fn (Role $record) => Str::title(str_replace('_', ' ', $record->base_name)))
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('name', $direction)),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->mutateDataUsing(fn (array $data): array => [...$data, 'name' => Role::combineName($data['name'], $data['grade'])])
                        ->after(fn (Role $record, array $data) => $record->syncPermissions($data['permissions'] ?? [])),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
