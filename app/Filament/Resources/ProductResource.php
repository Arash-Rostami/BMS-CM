<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Master\ProductResource\Exports\ProductExporter;
use App\Filament\Resources\Master\ProductResource\Pages\ManageProducts;
use App\Filament\Resources\Master\ProductResource\Traits\CategoryDrilldown;
use App\Filament\Resources\Master\ProductResource\Traits\Filters as ProductFilters;
use App\Filament\Resources\Master\ProductResource\Traits\Form as ProductForm;
use App\Filament\Resources\Master\ProductResource\Traits\Infolist as ProductInfolist;
use App\Filament\Resources\Master\ProductResource\Traits\Table as ProductTable;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\Product;
use App\Services\SmartCacheManager;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    use ProductForm, ProductTable, ProductInfolist, ProductFilters, CategoryDrilldown, HasResourcePermissions;


    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 2;


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('action')
                    ->options([
                        'check' => __('resources/product/strings.form.action_check'),
                        'create' => __('resources/product/strings.form.action_create'),
                    ])
                    ->required()
                    ->live()
                    ->visibleOn('create')
                    ->validationMessages([
                        'required' => __('resources/product/strings.form.validation_action_required'),
                    ])
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
                        Tab::make('1')
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
                        Tab::make('2')
                            ->label(__('resources/product/strings.form.tab2'))
                            ->schema([
                                Section::make(__('resources/product/strings.form.specifications_section_title'))
                                    ->schema([
                                        Repeater::make('specifications')
                                            ->hiddenLabel()
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
                    ])
                    ->columnSpanFull()
                    ->visible(fn(Get $get, $operation) => $get('action') === 'create' || $operation == 'edit')
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'creator',
                'updater',
                'category',
                'category.ancestors',
                'invoiceItems',
                'purchaseItems',
                'specifications',
                'targets',
            ])
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $date = toYmdDate($record);
        $name = $record->getLocalizedNameAttribute() ?? '-';

        return "📦   {$name} (📆 {$date})";
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('index', ['search' => $record->english_name ?? $record->name ?? '']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'english_name'];
    }

    public static function getModelLabel(): string
    {
        return __('resources/product/strings.general.model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SmartCacheManager::remember(
            'Product',
            ['user_id' => auth()->id(), 'type' => 'total_count'],
            3600,
            fn() => static::getModel()::count()
        );

        return $count > 0 ? (string)$count : null;
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
            'index' => ManageProducts::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/product/strings.general.plural_model_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Product Information')
                    ->tabs([
                        //Product
                        Tab::make('General')
                            ->label(__('resources/product/strings.form.tab1'))
                            ->icon('heroicon-o-cube')
                            ->schema([
                                Section::make()->schema([
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
                                Section::make()
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
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->mutateDataUsing(fn(array $data) => ManageProducts::setSlugAndCategory($data)),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()->exporter(ProductExporter::class),
                ])
            ])
            ->striped()
            ->searchDebounce('1000ms')
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
