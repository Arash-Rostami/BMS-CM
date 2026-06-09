<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\FormComponents;
use App\Filament\Resources\General\InfoComponents;
use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Operational\RegisteredOrderResource\Exports\RegisteredOrderExporter;
use App\Filament\Resources\Operational\RegisteredOrderResource\Pages\CreateRegisteredOrder;
use App\Filament\Resources\Operational\RegisteredOrderResource\Pages\EditRegisteredOrder;
use App\Filament\Resources\Operational\RegisteredOrderResource\Pages\ListRegisteredOrders;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\BankProfilesRelationManager;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\CorrespondenceRelationManager;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\CustomsRelationManager;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\ProformaInvoicesRelationManager;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\PurchaseOrdersRelationManager;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\PurchaseRequestsRelationManager;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\ShipmentsRelationManager;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\Filters as RegisteredOrderFilters;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\Form as RegisteredOrderForm;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\Infolist as RegisteredOrderInfolist;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\Table as RegisteredOrderTable;
use App\Filament\Traits\HasExtraAttributesManagement;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\RegisteredOrder;
use App\Services\SmartCacheManager;
use BackedEnum;
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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table as FilamentTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;


class RegisteredOrderResource extends Resource
{
    use RegisteredOrderForm, RegisteredOrderTable, RegisteredOrderInfolist, RegisteredOrderFilters, HasResourcePermissions, HasExtraAttributesManagement;

    protected static ?string $model = RegisteredOrder::class;


    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';


    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Registered Order')
                    ->tabs([
                        Tab::make(__('resources/registeredOrder/strings.form.tabs.general'))
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/registeredOrder/strings.form.section_order_details'))
                                            ->schema([
                                                static::getSourceTypeField(),
                                                static::getProformaInvoicesField(),
                                                static::getPurchaseOrdersField(),
                                                static::getPurchaseRequestsField(),
                                                static::getRoNumberField(),
                                                static::getContractNumberField(),
                                                static::getOfficialRegistrationNoField(),
                                                static::getStatusField(),
                                                static::getOrderDateField(),
                                                static::getValidityDateField(),
                                                static::getCurrencyField(),
                                                static::getSellerField(),
                                                static::getBuyerField(),
                                            ])->columns(3),

                                        Section::make(__('resources/registeredOrder/strings.form.section_line_items'))
                                            ->schema([
                                                Repeater::make('items')
                                                    ->relationship()
                                                    ->hiddenLabel()
                                                    ->schema([
                                                        static::getItemProductIdField(),
                                                        static::getItemQuantityField(),
                                                        static::getItemUnitPriceField(),
                                                        static::getItemUnitField(),
                                                        static::getItemEntranceFeeField(),
                                                        static::getItemShippingCostField(),
                                                        static::getItemExtraCostField(),
                                                        static::getItemNetWeightField(),
                                                        static::getItemGrossWeightField(),
                                                        static::getItemLineTotalField(),
                                                        static::getItemPackingDetailsField(),
                                                        static::getItemNotesToggle(),
                                                        static::getItemDescriptionField(),
                                                    ])
                                                    ->columns(15)
                                                    ->defaultItems(0)
                                                    ->live()
                                                    ->addActionLabel(__('resources/registeredOrder/strings.form.add_item'))
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::updateTotal($get, $set))
                                                    ->afterStateHydrated(fn(Get $get, Set $set) => static::updateTotal($get, $set))
                                                    ->deleteAction(fn($action) => $action->after(fn(Get $get, Set $set) => static::updateTotal($get, $set)))
                                                    ->addAction(fn($action) => $action->after(fn(Get $get, Set $set) => static::updateTotal($get, $set)))
                                                    ->mutateRelationshipDataBeforeFillUsing(fn(array $data): array => $data)
                                            ]),
                                    ])
                                    ->columnSpan(['lg' => 2]),

                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/registeredOrder/strings.form.section_summary_notes'))
                                            ->schema([
                                                Grid::make(2)->schema([
                                                    static::getTotalQuantityField(),
                                                    static::getTotalAmountField(),
                                                ]),
                                                static::getNotesField(),
                                                FormComponents::getAttachmentsField(),
                                            ]),
                                    ])
                                    ->columnSpan(['lg' => 1]),
                            ])->columns(3),

                        Tab::make(__('resources/registeredOrder/strings.form.tabs.insurance'))
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make(__('resources/registeredOrder/strings.form.section_insurance_details'))
                                    ->schema([
                                        static::getInsuranceNumberField(),
                                        static::getInsuranceProviderField(),
                                        static::getInsuranceDateField(),
                                        static::getCurrencyTypeField(),
                                        static::getIncotermsField(),
                                        static::getExpectedDeliveryDateField(),
                                    ])->columns(3),
                            ]),
                        static::getExtraAttributesFormTab(),
                    ])
                    ->columnSpan('full'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'creator',
                'updater',
                'attachments',
                'extraAttributes',
                'items',
                'items.product',
                'currency',
                'purchaseRequests',
                'proformaInvoices',
                'purchaseOrders',
                'status',
                'buyerCompany',
                'sellerCompany',
                'sellerCompanyExclusive',
                'supplierCompanyExclusive',
                'manufacturerCompanyExclusive',
                'shipments'
            ])
            ->withCount([
                'purchaseOrders',
                'proformaInvoices',
                'purchaseRequests',
                'shipments',
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $date = toYmdDate($record);
        $ro = $record->ro_number ?? $record->contract_no ?? '—';

        return "📝 {$ro} (📆 {$date})";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['ro_number', 'contract_no', 'official_registration_no'];
    }

    public static function getModelLabel(): string
    {
        return __('resources/registeredOrder/strings.general.model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SmartCacheManager::remember(
            'RegisteredOrder',
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
        return __('resources/dashboard/strings.navigation_group.operational_second');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegisteredOrders::route('/'),
            'create' => CreateRegisteredOrder::route('/create'),
            'edit' => EditRegisteredOrder::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/registeredOrder/strings.general.plural_model_label');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PurchaseRequestsRelationManager::class,
            ProformaInvoicesRelationManager::class,
            BankProfilesRelationManager::class,
            PurchaseOrdersRelationManager::class,
            PaymentsRelationManager::class,
            ShipmentsRelationManager::class,
            CustomsRelationManager::class,
            CorrespondenceRelationManager::class,
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Details')->tabs([
                    Tab::make(__('resources/registeredOrder/strings.infolist.tab_general'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Section::make()->schema([
                                InfoComponents::viewPurchaseRequests(),
                                InfoComponents::viewProformaInvoices(),
                                InfoComponents::viewPurchaseOrders(),
                                static::viewRoNumber(),
                                static::viewCtNumber(),
                                static::viewOfficialRegistrationNo(),
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
                                static::viewNotes(),
                                static::viewCreator(),
                                static::viewUpdater(),
                                static::viewCreatedAt(),
                                static::viewUpdatedAt(),
                            ])->columns(3),
                        ]),
                    Tab::make(__('resources/registeredOrder/strings.infolist.tab_items'))
                        ->icon('heroicon-o-list-bullet')
                        ->badge(fn($record) => $record->items->count())
                        ->schema([
                            Section::make()->schema([
                                RepeatableEntry::make('items')
                                    ->label(__('resources/registeredOrder/strings.infolist.line_items'))
                                    ->schema([
                                        static::viewItemProduct(),
                                        static::viewItemUnitPrice(),
                                        static::viewItemQuantity(),
                                        static::viewItemUnit(),
                                        static::viewItemNetWeight(),
                                        static::viewItemGrossWeight(),
                                    ])->columns(7),
                            ]),
                        ]),
                    Tab::make(__('resources/registeredOrder/strings.infolist.tab_documents'))
                        ->icon('heroicon-o-paper-clip')
                        ->schema([Section::make()->schema([static::viewAttachments()])])
                        ->label(fn($record) => tabBadge(
                            __('resources/registeredOrder/strings.infolist.tab_documents'),
                            $record?->attachments->count() ?? 0,
                            'info'
                        )),
                    static::getExtraAttributesInfolistTab(),
                ])->columnSpanFull(),
            ]);
    }


    public static function table(FilamentTable $table): FilamentTable
    {
        return $table
            ->columns([
                static::showSource(),
                static::showId(),
                TableComponents::showPurchaseRequests(),
                TableComponents::showProformaInvoices(),
                TableComponents::showPurchaseOrders(),
                static::showRoNumber(),
                static::showCtNumber(),
                static::showOfficialRegistrationNo(),
                static::showSeller(),
                static::showBuyer(),
                static::showStatus(),
                static::showOrderDate(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
                static::showPurchaseOrdersCount(),
            ])
            ->filters([
                static::getSellerFilter(),
                static::getBuyerFilter(),
                static::getStatusFilter(),
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
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                        RestoreBulkAction::make(),
                        ExportBulkAction::make()
                            ->exporter(RegisteredOrderExporter::class),
                    ]),
                ]),
            ])
            ->groups([
                TableGroup::make('buyerCompany.name')
                    ->label(__('resources/registeredOrder/strings.filters.buyer'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'buyerCompany')),
                TableGroup::make('sellerCompanyExclusive.name')
                    ->label(__('resources/registeredOrder/strings.filters.seller'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'sellerCompanyExclusive')),
                TableGroup::make('supplierCompanyExclusive.name')
                    ->label(__('resources/registeredOrder/strings.filters.supplier'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'supplierCompanyExclusive')),
                TableGroup::make('manufacturerCompanyExclusive.name')
                    ->label(__('resources/registeredOrder/strings.filters.manufacturer'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'manufacturerCompanyExclusive')),
            ])
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
