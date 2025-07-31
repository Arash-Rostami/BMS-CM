<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Master\ProductResource\Exports\ProductExporter;
use App\Filament\Resources\Master\ProductResource\Pages\ManageProducts;
use App\Filament\Resources\Master\ProductResource\Traits\CategoryDrilldown;
use App\Filament\Resources\Master\ProductResource\Traits\Filters as ProductFilters;
use App\Filament\Resources\Master\ProductResource\Traits\Form as ProductForm;
use App\Filament\Resources\Master\ProductResource\Traits\Infolist as ProductInfolist;
use App\Filament\Resources\Master\ProductResource\Traits\Table as ProductTable;
use App\Models\Product;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components;
use Filament\Infolists\Components\Tabs as InfoTabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    use ProductForm, ProductTable, ProductInfolist, ProductFilters, CategoryDrilldown;


    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 2;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('action')
                    ->options([
                        'check' => __('resources/product/strings.form.action_check'),
                        'create' => __('resources/product/strings.form.action_create'),
                    ])
                    ->required()
                    ->reactive()
                    ->visibleOn('create')
                    ->label(__('resources/product/strings.form.choose_action')),
                //checking code
                Group::make([
                    Section::make(__('resources/product/strings.form.action_check'))
                        ->schema([
                            self::doubleCheckCode(),
                            self::enquiryResponse(),
                        ])
                        ->columns(2),
                ])->columnSpanFull()->visible(fn(Get $get) => $get('action') === 'check'),
                //creating new product
                Tabs::make('Tabs')
                    ->tabs([
                        //Category & Details
                        Tabs\Tab::make('1')
                            ->label(__('resources/product/strings.form.tab1'))
                            ->schema([
                                Section::make(__('resources/product/strings.form.category'))
                                    ->schema(static::getAllFields())
                                    ->columns(2),
                                Section::make(__('resources/product/strings.form.attributes'))
                                    ->schema([
                                        static::getCodeField(),
                                        static::getAttributesJsonField(),
                                        Group::make([
                                            static::getInStockField(),
                                            static::getIsActive(),
                                            static::getClassificationOptions(),
                                        ])->columns(3)->columnSpanFull(),
                                        static::getNameField(),
                                        static::getEnglishNameField(),
                                        static::getSlugField(),
                                        static::getDescriptionField(),
                                    ])
                                    ->visible(fn($get) => $get('chain_complete') === true)
                                    ->columns(2),
                            ])
                            ->icon('heroicon-o-cube'),
                        //Specifications
                        Tabs\Tab::make('2')
                            ->label(__('resources/product/strings.form.tab2'))
                            ->schema([
                                Section::make(__('resources/product/strings.form.specifications_section_title'))
                                    ->schema([
                                        Repeater::make('specifications')
                                            ->label('')
                                            ->relationship('specifications')
                                            ->maxItems(1)
                                            ->deletable()
                                            ->columns(2)
                                            ->schema([
                                                static::getHsCode(),
                                                static::getImportDuty(),
                                                static::getPackagingType(),
                                                static::getVAT(),
                                                static::getTaxId(),
                                                static::getManufacturer(),
                                                static::getImportLicense(),
                                                static::getExtra(),
                                            ]),
                                    ]),
                            ])->icon('heroicon-o-list-bullet'),
                    ])->columnSpanFull()
                    ->visible(fn(Get $get, $operation) => $get('action') === 'create' || $operation == 'edit')
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::showName(),
                static::showEnglishName(),
                static::showCode(),
                static::showCategory(),
                static::showRollSheetType(),
                static::showProductAttributes(),
                static::showInStock(),
                static::showIsActive(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getActiveFilter(),
                static::getCategoryFilter(),
                static::getInStockFilter(),
                static::getRollSheetFilter(),
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
                InfoTabs::make('Product Information')
                    ->tabs([
                        //Product
                        Tab::make('General')
                            ->label(__('resources/product/strings.form.tab1'))
                            ->icon('heroicon-o-cube')
                            ->schema([
                                Components\Section::make()->schema([
                                    static::viewName(),
                                    static::viewEnglishName(),
                                    static::viewCode(),
                                    static::viewCategory(),
                                    static::viewAttributesJson(),
                                    static::viewInStock(),
                                    static::viewIsActive(),
                                    static::viewSlug(),
                                    static::viewDescription(),
                                    static::viewCreator(),
                                    static::viewUpdater(),
                                    static::viewCreatedAt(),
                                    static::viewUpdatedAt(),
                                ])->columns(2),
                            ]),
                        //Specifications
                        Tab::make('Specifications')
                            ->label(__('resources/product/strings.form.tab2'))
                            ->icon('heroicon-o-list-bullet')
                            ->schema([
                                Components\Section::make()
                                    ->schema([
                                        static::viewHsCode(),
                                        static::viewImportDuty(),
                                        static::viewPackingType(),
                                        static::viewVatExempt(),
                                        static::viewTaxId(),
                                        static::viewManufacturer(),
                                        static::viewImportLicenses(),
                                        static::viewExtra(),
                                        static::viewSpecCreator(),
                                        static::viewSpecUpdater(),
                                        static::viewSpecCreatedAt(),
                                        static::viewSpecUpdatedAt(),
                                    ])->columns(2)
                            ]),
                    ])
                    ->columnSpanFull(),
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
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with('category.ancestors');
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
