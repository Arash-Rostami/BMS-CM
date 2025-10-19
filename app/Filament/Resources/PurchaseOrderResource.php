<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\ListPurchaseOrders;
use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\CreatePurchaseOrder;
use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\EditPurchaseOrder;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\ProformaInvoicesRelationManager;
use App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers\PurchaseRequestsRelationManager;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ExportBulkAction;
use App\Filament\Resources\Operational\PurchaseOrderResource\Exports\PurchaseOrderExporter;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\Filters as PurchaseOrderFilters;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\Form as PurchaseOrderForm;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\Infolist as PurchaseOrderInfolist;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\Table as PurchaseOrderTable;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\TotalCalculation;
use App\Models\PurchaseOrder;
use App\Services\SmartCacheManager;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;


class PurchaseOrderResource extends Resource
{
    use TotalCalculation, PurchaseOrderForm, PurchaseOrderTable, PurchaseOrderFilters, PurchaseOrderInfolist;

    protected static ?string $model = PurchaseOrder::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string | \UnitEnum | null $navigationGroup = 'Operational';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Group::make()
                    ->schema([
                        Section::make(__('resources/purchaseOrder/strings.form.section_order_details'))
                            ->schema([
                                static::getPurchaseRequestsField(),
                                static::getPoNumberField(),
                                static::getOrderDateField(),
                                static::getStatusField(),
                                static::getSupplierField(),
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
                                    ->relationship()
                                    ->schema([
                                        static::getItemProductIdField(),
                                        static::getItemUnitField(),
                                        static::getItemQuantityField(),
                                        static::getItemUnitPriceField(),
                                        static::getItemNetWeightField(),
                                        static::getItemGrossWeightField(),
                                    ])
                                    ->columns(8)
                                    ->defaultItems(1)
                                    ->live()
                                    ->afterStateHydrated(fn($component, $state, $get) => ($items = $get('items')) ? $component->state($items) : null)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotal($get, $set))
                                    ->addActionLabel(__('resources/purchaseOrder/strings.form.add_item_action'))
                                    ->label(false)
                                    ->deleteAction(fn($action) => $action->after(fn(Get $get, Set $set) => self::updateTotal($get, $set)))]),
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
                                static::getAttachmentsField(),
                            ])->columns(4)
                        ,
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
                'buyer',
                'currency',
                'items',
                'proformaInvoices',
                'purchaseRequests',
                'status',
                'supplier',
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "🛍️ " . $record->po_number;
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
            ProformaInvoicesRelationManager::class,
            PurchaseRequestsRelationManager::class,
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
                            static::viewPoNumber(),
                            static::viewOrderDate(),
                            static::viewValidityDate(),
                            static::viewExpectedDeliveryDate(),
                            static::viewCurrency(),
                            static::viewIncoterms(),
                            static::viewSupplier(),
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
                static::showID(),
                static::showPurchaseRequests(),
                static::showPoNumber(),
                static::showSupplier(),
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
                static::getSupplierFilter(),
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
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(PurchaseOrderExporter::class),
                ]),
            ])
            ->groups([
                Group::make('buyer.name')
                    ->label(__('resources/purchaseOrder/strings.filters.buyer'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'buyer')),
                Group::make('supplier.name')
                    ->label(__('resources/purchaseOrder/strings.filters.supplier'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'supplier')),
            ])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('id', 'desc');
    }
}
