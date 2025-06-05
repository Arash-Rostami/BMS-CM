<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Master\ProductResource\Exports\ProductExporter;
use App\Filament\Resources\Master\ProductResource\Pages\ManageProducts;
use App\Filament\Resources\Master\ProductResource\Traits\CategoryDrilldown;
use App\Filament\Resources\Master\ProductResource\Traits\Form as ProductForm;
use App\Filament\Resources\Master\ProductResource\Traits\Table as ProductTable;
use App\Filament\Resources\Master\ProductResource\Traits\InfoList as ProductInfolist;
use App\Filament\Resources\Master\ProductResource\Traits\Filters as ProductFilters;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\ActionGroup;
use App\Models\Product;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    use ProductForm, ProductTable, ProductInfolist, ProductFilters, CategoryDrilldown;


    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Category')
                    ->schema(static::getAllFields())
                    ->columns(2),

                Section::make('Details')
                    ->schema([
                        static::getCodeField(),
                        static::getAttributesJsonField(),
                        static::getInStockField(),
                        static::getClassificationOptions(),
                        static::getNameField(),
                        static::getEnglishNameField(),
                        static::getSlugField(),
                        static::getDescriptionField(),
                    ])
                    ->visible(fn($get) => $get('chain_complete') === true)
                    ->columns(2),
            ])
            ->columns(2);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::showName(),
                static::showEnglishName(),
                static::showCode(),
                static::showCategory(),
                static::showProductAttributes(),
                static::showInStock(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getCategoryFilter(),
                static::getInStockFilter(),
                static::getCreatorFilter(),
                static::getUpdaterFilter(),
                static::getTrashedFilter(),
            ])->filtersFormColumns(2)
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->mutateFormDataUsing(fn(array $data) => ManageProducts::setSlugAndCategory($data)),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ExportBulkAction::make()->exporter(ProductExporter::class),
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
                        static::viewCode(),
                        static::viewCategory(),
                        static::viewAttributesJson(),
                        static::viewInStock(),
                        static::viewSlug(),
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
            'index' => ManageProducts::route('/'),

        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getModelLabel(): string
    {
        return __('resources/product/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/product/strings.general.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/product/strings.general.navigation_group');
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
        return "📦 " . $record->getLocalizedNameAttribute();
    }
}
