<?php

namespace App\Filament\Resources;


use App\Filament\Resources\Master\CategoryResource\Exports\CategoryExporter;
use App\Filament\Resources\Master\CategoryResource\Traits\Form as CategoryForm;
use App\Filament\Resources\Master\CategoryResource\Traits\Table as CategoryTable;
use App\Filament\Resources\Master\CategoryResource\Traits\InfoList as CategoryInfolist;
use App\Filament\Resources\Master\CategoryResource\Traits\Filters as CategoryFilters;
use Filament\Tables\Actions\ActionGroup;
use App\Filament\Resources\Master\CategoryResource\RelationManagers;
use App\Models\Category;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CategoryResource extends Resource
{
    use CategoryForm, CategoryTable, CategoryInfolist, CategoryFilters;

    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form->schema([
            static::getName(),
            static::getEnglishName(),
            static::getLevel(),
            static::getParentCategory(),
            static::getActive(),
            static::getDescription(),
        ])->columns(2);
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
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ExportBulkAction::make()->exporter(CategoryExporter::class)
                ])
            ])
            ->striped()
            ->defaultSort('id', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make()
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
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Master\CategoryResource\Pages\ManageCategories::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getModelLabel(): string
    {
        return __('resources/category/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/category/strings.general.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/category/strings.general.navigation_group');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "📁 " . $record->getLocalizedNameAttribute();
    }
}
