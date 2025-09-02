<?php

namespace App\Filament\Resources;

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
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;


class PurchaseOrderResource extends Resource
{
    use TotalCalculation, PurchaseOrderForm, PurchaseOrderTable, PurchaseOrderFilters, PurchaseOrderInfolist;

    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Operational';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('resources/purchaseOrder/strings.form.section_order_details'))
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


                        Forms\Components\Section::make(__('resources/purchaseOrder/strings.form.section_items'))
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

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('resources/purchaseOrder/strings.form.section_shipping_notes'))
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
        return __('resources/purchaseOrder/strings.general.navigation_group');
    }

    public static function getPages(): array
    {
        return [
            'index' => Operational\PurchaseOrderResource\Pages\ListPurchaseOrders::route('/'),
            'create' => Operational\PurchaseOrderResource\Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Operational\PurchaseOrderResource\Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/purchaseOrder/strings.general.plural_model_label');
    }

    public static function getRelations(): array
    {
        return [
            Operational\PurchaseOrderResource\RelationManagers\ProformaInvoicesRelationManager::class,
            Operational\PurchaseOrderResource\RelationManagers\PurchaseRequestsRelationManager::class,
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Tabs::make('Details')->tabs([
                Tabs\Tab::make(__('resources/purchaseOrder/strings.infolist.tab_general'))
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
                Tabs\Tab::make(__('resources/purchaseOrder/strings.infolist.tab_items'))
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
                Tabs\Tab::make('Documents')
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
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ExportBulkAction::make()
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
