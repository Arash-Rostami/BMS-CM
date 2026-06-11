<?php

namespace App\Filament\Resources;


use App\Filament\Resources\Master\CategoryResource\Exports\CategoryExporter;
use App\Filament\Resources\Master\CategoryResource\Pages\ManageCategories;
use App\Filament\Resources\Master\CategoryResource\RelationManagers;
use App\Filament\Resources\Master\CategoryResource\Traits\Filters as CategoryFilters;
use App\Filament\Resources\Master\CategoryResource\Traits\Form as CategoryForm;
use App\Filament\Resources\Master\CategoryResource\Traits\Infolist as CategoryInfolist;
use App\Filament\Resources\Master\CategoryResource\Traits\Table as CategoryTable;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\Category;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
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

class CategoryResource extends Resource
{
    use CategoryForm, CategoryTable, CategoryInfolist, CategoryFilters, HasResourcePermissions;

    protected static ?string $model = Category::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            static::getName(),
            static::getEnglishName(),
            static::getLevel(),
            static::getParentCategory(),
            static::getActive(),
            static::getDescription(),
        ])->columns(2);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'creator',
                'updater',
                'parent',
                'children',
                'ancestors',
                'descendants',
                'products',
                'specifications',
                'targets',
            ])
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $date = toYmdDate($record);
        $name = $record->getLocalizedNameAttribute() ?? '-';

        return "📁   {$name} (📆 {$date})";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'english_name'];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('index', ['search' => $record->english_name ?? $record->name ?? '']);
    }

    public static function getModelLabel(): string
    {
        return __('resources/category/strings.general.model_label');
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.base');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/category/strings.general.plural_model_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::viewName(),
                        static::viewEnglishName(),
                        static::viewParent(),
                        static::viewActive(),
                        static::viewLevel(),
                        static::viewDescription(),
                        static::viewCreator(),
                        static::viewUpdater(),
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
                static::showEnglishName(),
                static::showLevel(),
                static::showParent(),
                static::showActive(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getLevelFilter(),
                static::getActiveFilter(),
                static::getAncestorsFilter(),
                static::getDescendantsFilter(),
                static::getTrashedFilter(),
                static::getCreatorFilter(),
                static::getUpdaterFilter(),
            ])->filtersFormColumns(2)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()->exporter(CategoryExporter::class)
                ])
            ])
            ->striped()
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
