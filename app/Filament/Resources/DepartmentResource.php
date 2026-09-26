<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Master\DepartmentResource\Pages\ManageDepartments;
use App\Filament\Resources\Master\DepartmentResource\Traits\Filters as DepartmentFilters;
use App\Filament\Resources\Master\DepartmentResource\Traits\Form as DepartmentForm;
use App\Filament\Resources\Master\DepartmentResource\Traits\Infolist as DepartmentInfolist;
use App\Filament\Resources\Master\DepartmentResource\Traits\Table as DepartmentTable;
use App\Filament\Traits\HandleActivation;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\Department;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DepartmentResource extends Resource
{
    use DepartmentFilters, DepartmentForm, DepartmentInfolist, DepartmentTable, HandleActivation, HasResourcePermissions;

    protected static ?string $model = Department::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::getName(),
                        static::getCode(),
                        static::getEnglishName(),
                        static::getIsActive(),
                        static::getDescription(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'creator',
                'updater',
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $name = $record->getLocalizedNameAttribute() ?? '-';

        return "🏢  {$name} (🔑 {$record->code})";
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('index', ['search' => $record->english_name ?? $record->name ?? '']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'english_name', 'code'];
    }

    public static function getModelLabel(): string
    {
        return __('resources/department/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.base');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDepartments::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/department/strings.general.plural_model_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::viewName(),
                        static::viewEnglishName(),
                        static::viewCode(),
                        static::viewIsActive(),
                        static::viewCreator(),
                        static::viewUpdater(),
                        static::viewCreatedAt(),
                        static::viewUpdatedAt(),
                        static::viewDescription(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return TableComponents::emptyState($table
            ->columns([
                static::showCode(),
                static::showName(),
                static::showEnglishName(),
                static::showDescription(),
                static::showIsActive(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getActiveFilter(),
                static::getThrashedFilter(),
                static::getCreatorFilter(),
                static::getUpdaterFilter(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::getExportBulkAction(),
                    static::getActivateBulkAction()->authorize(fn (): bool => static::canEditAny()),
                    static::getDeactivateBulkAction()->authorize(fn (): bool => static::canEditAny()),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->striped()
            ->reorderableColumns()
            ->defaultSort('id', 'desc'));
    }
}
