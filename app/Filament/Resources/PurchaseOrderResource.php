<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\FormComponents;
use App\Filament\Resources\General\InfoComponents;
use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Operational\PurchaseOrderResource\Exports\PurchaseOrderExporter;
use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\CreatePurchaseOrder;
use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\EditPurchaseOrder;
use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\ListPurchaseOrders;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\ProformaInvoicesRelationManager;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\PurchaseRequestsRelationManager;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\RegisteredOrdersRelationManager;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\Filters as PurchaseOrderFilters;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\Form as PurchaseOrderForm;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\Infolist as PurchaseOrderInfolist;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\Table as PurchaseOrderTable;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\TotalCalculation;
use App\Models\PurchaseOrder;
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
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;


class PurchaseOrderResource extends Resource
{
    use TotalCalculation, PurchaseOrderForm, PurchaseOrderTable, PurchaseOrderFilters, PurchaseOrderInfolist;

    protected static ?string $model = PurchaseOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|\UnitEnum|null $navigationGroup = 'Operational';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Group::make()
                    ->schema([
                        Section::make(__('resources/purchaseOrder/strings.form.section_order_details'))
                            ->schema([
                                static::getSourceTypeField(),
                                static::getPurchaseRequestsField(),
                                static::getProformaInvoicesField(),
                                static::getRegisteredOrdersField(),
                                static::getPoNumberField(),
                                static::getOrderDateField(),
                                static::getStatusField(),
                                static::getsellerField(),
                                static::getBuyerField(),
                                static::getIncotermsField(),
                                static::getCurrencyField(),
                                static::getValidityDateField(),
                                static::getExpectedDeliveryDateField(),
                            ])->columns(3),


                        Section::make(__('resources/purchaseOrder/strings.form.section_items'))
                            ->heading(__('resources/purchaseOrder/strings.form.section_items'))
                            ->schema([
                                Repeater::make('items')
                                    ->hiddenLabel()
                                    ->relationship()
                                    ->schema([
                                        static::getItemProductIdField(),
                                        static::getItemUnitField(),
                                        static::getItemQuantityField(),
                                        static::getItemUnitPriceField(),
                                        static::getItemNetWeightField(),
                                        static::getItemGrossWeightField(),
                                        static::getItemNotesToggle(),
                                        static::getItemDescriptionField(),
                                    ])
                                    ->columns(8)
                                    ->defaultItems(0)
                                    ->live(true)
                                    ->addActionLabel(__('resources/purchaseOrder/strings.form.add_item_action'))
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotal($get, $set))
                                    ->afterStateHydrated(fn(Get $get, Set $set) => static::updateTotal($get, $set))
//                                  ->afterStateHydrated(fn($component, $state, $get) => ($items = $get('items')) ? $component->state($items) : null)
                                    ->mutateRelationshipDataBeforeFillUsing(fn(array $data): array => $data)
                                    ->addAction(fn($action) => $action->after(fn(Get $get, Set $set) => static::updateTotal($get, $set)))
                                    ->deleteAction(fn($action) => $action->after(fn(Get $get, Set $set) => self::updateTotal($get, $set)))])
                    ])
                    ->columnSpan(['lg' => 2]),

                \Filament\Schemas\Components\Group::make()
                    ->schema([
                        Section::make(__('resources/purchaseOrder/strings.form.section_shipping_notes'))
                            ->schema([
                                static::getTotalQuantityField(),
                                static::getTotalAmountField(),
                                static::getPackingDetailsField(),
                                static::getShippingAddressField(),
                                static::getNotesField(),
                                FormComponents::getAttachmentsField(),
                            ])->columns(4)
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'creator',
                'updater',
                'attachments',
                'currency',
                'items',
                'items.product',
                'proformaInvoices',
                'purchaseRequests',
                'registeredOrders',
                'status',
                'buyerCompany',
                'sellerCompany',
                'sellerCompanyExclusive',
                'supplierCompanyExclusive',
                'manufacturerCompanyExclusive'
            ])
            ->withCount([
                'proformaInvoices',
                'registeredOrders',
                'purchaseRequests',
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $date = toYmdDate($record);
        $po = $record->po_number ?? '—';

        return "🛍️ {$po} (📆 {$date})";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['po_number'];
    }

    public static function getModelLabel(): string
    {
        return __('resources/purchaseOrder/strings.general.model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SmartCacheManager::remember(
            'PurchaseOrder',
            ['user_id' => auth()->id(), 'type' => 'total_count'],
            150,
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
        return __('resources/dashboard/strings.navigation_group.operational_third');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/purchaseOrder/strings.general.plural_model_label');
    }

    public static function getRelations(): array
    {
        return [
            PurchaseRequestsRelationManager::class,
            ProformaInvoicesRelationManager::class,
            RegisteredOrdersRelationManager::class,
            PaymentsRelationManager::class
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Details')->tabs([
                Tab::make(__('resources/purchaseOrder/strings.infolist.tab_general'))
                    ->icon('heroicon-o-shopping-bag')
                    ->schema([
                        Section::make()->schema([
                            InfoComponents::viewPurchaseRequests(),
                            InfoComponents::viewProformaInvoices(),
                            InfoComponents::viewRegisteredOrders(),
                            static::viewPoNumber(),
                            static::viewOrderDate(),
                            static::viewValidityDate(),
                            static::viewExpectedDeliveryDate(),
                            static::viewCurrency(),
                            static::viewIncoterms(),
                            static::viewSeller(),
                            static::viewBuyer(),
                            static::viewStatus(),
                            static::viewTotalAmount(),
                            static::viewTotalQuantity(),
                            static::viewPackingDetails(),
                            static::viewShippingAddress(),
                            static::viewNotes(),
                            static::viewCreator(),
                            static::viewUpdater(),
                            static::viewCreatedAt(),
                            static::viewUpdatedAt(),
                        ])->columns(3),
                    ]),
                Tab::make(__('resources/purchaseOrder/strings.infolist.tab_items'))
                    ->icon('heroicon-o-list-bullet')
                    ->badge(fn($record) => $record->items->count())
                    ->schema([
                        Section::make()->schema([
                            RepeatableEntry::make('items')
                                ->label(__('resources/purchaseOrder/strings.infolist.purchase_items'))
                                ->schema([
                                    self::viewItemProduct(),
                                    self::viewItemUnitPrice(),
                                    self::viewItemQuantity(),
                                    self::viewItemUnit(),
                                    self::viewItemNetWeight(),
                                    self::viewItemGrossWeight(),
                                ])->columns(7)
                        ]),
                    ]),
                Tab::make('Documents')
                    ->label(__('resources/purchaseOrder/strings.infolist.tab_documents'))
                    ->icon('heroicon-o-paper-clip')
                    ->schema([Section::make()->schema([static::viewAttachments()])])
                    ->badge(fn($record) => $record->attachments->count())
                    ->badgeColor('info'),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::showSource(),
                static::showID(),
                TableComponents::showPurchaseRequests(),
                TableComponents::showProformaInvoices(),
                TableComponents::showRegisteredOrders(),
                static::showPoNumber(),
                static::showSeller(),
                static::showBuyer(),
                static::showStatus(),
                static::showTotalAmount(),
                static::showOrderDate(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getSellerFilter(),
                static::getBuyerFilter(),
                static::getStatusFilter(),
                static::getIncotermsFilter(),
                static::getCurrencyFilter(),
                static::getCreatorFilter(),
                static::getTrashedFilter(),
                static::getCreationDateFilter(),
            ])
            ->filtersFormColumns(3)
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
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(PurchaseOrderExporter::class),
                ]),
            ])
            ->groups([
                Group::make('buyerCompany.name')
                    ->label(__('resources/purchaseOrder/strings.filters.buyer'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'buyerCompany')),
                Group::make('sellerCompanyExclusive.name')
                    ->label(__('resources/purchaseOrder/strings.filters.seller'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'sellerCompanyExclusive')),
                Group::make('supplierCompanyExclusive.name')
                    ->label(__('resources/purchaseOrder/strings.filters.supplier'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'supplierCompanyExclusive')),
                Group::make('manufacturerCompanyExclusive.name')
                    ->label(__('resources/purchaseOrder/strings.filters.manufacturer'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'manufacturerCompanyExclusive')),
            ])
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('purchase_orders.id', 'desc');
    }
}
