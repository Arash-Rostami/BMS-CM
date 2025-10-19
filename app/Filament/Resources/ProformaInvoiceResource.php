<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Pages\ListProformaInvoices;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Pages\CreateProformaInvoice;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Pages\EditProformaInvoice;
use App\Filament\Resources\Operational\ProformaInvoiceResource\RelationManagers\PurchaseOrdersRelationManager;
use App\Filament\Resources\Operational\ProformaInvoiceResource\RelationManagers\PurchaseRequestsRelationManager;
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
use App\Filament\Resources\Operational\ProformaInvoiceResource\Exports\ProformaInvoiceExporter;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Pages;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\Filters as ProformaInvoiceFilters;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\Form as ProformaInvoiceForm;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\Infolist as ProformaInvoiceInfolist;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\Table as ProformaInvoiceTable;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\TotalAmountCalculation;
use App\Models\ProformaInvoice;
use App\Services\SmartCacheManager;
use Filament\Forms;
use Filament\Infolists\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProformaInvoiceResource extends Resource
{
    use ProformaInvoiceForm, TotalAmountCalculation, ProformaInvoiceTable, ProformaInvoiceFilters, ProformaInvoiceInfolist;

    protected static ?string $model = ProformaInvoice::class;

    protected static ? string $recordTitleAttribute = 'title';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Proforma Invoice')
                    ->tabs([
                        Tab::make(__('resources/proformaInvoice/strings.form.tabs.general'))
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                \Filament\Schemas\Components\Group::make()
                                    ->schema([
                                        Section::make(__('resources/proformaInvoice/strings.form.invoice_details'))
                                            ->schema([
                                                static::getSourceTypeField(),
                                                static::getPurchaseRequestsField(),
                                                static::getPurchaseOrdersField(),
                                                static::getInvoiceNoField(),
                                                static::getInvoiceDateField(),
                                                static::getSellerCompanyIdField(),
                                                static::getConsigneeCompanyIdField(),
                                            ])->columns(2),

                                        Section::make(__('resources/proformaInvoice/strings.form.invoice_items'))
                                            ->schema([
                                                Repeater::make('items')
                                                    ->relationship()
                                                    ->schema([
                                                        static::getItemProductIdField(),
                                                        static::getItemOriginField(),
                                                        static::getItemHsCodeField(),
                                                        static::getItemQuantityField(),
                                                        static::getItemUnitPriceField(),
                                                        static::getItemNetWeightField(),
                                                        static::getItemGrossWeightField(),
                                                        static::getItemTotalAmountField(),
                                                        static::getItemNotesToggle(),
                                                        static::getItemEnglishNotesToggle(),
                                                        static::getItemDescriptionField(),
                                                        static::getItemEnglishDescriptionField(),
                                                    ])
                                                    ->columns(4)
                                                    ->live(true)
                                                    ->afterStateHydrated(function ($component, $state, Get $get, Set $set) {
                                                        if ($items = $get('items')) {
                                                            $component->state($items);
                                                        }
                                                        self::updateTotalAmount($get, $set);
                                                    })
                                                    ->deleteAction(fn($action) => $action->after(fn(Get $get, Set $set) => self::updateTotalAmount($get, $set)))
                                                    ->addActionLabel(__('resources/proformaInvoice/strings.form.add_invoice_item'))
                                                    ->label(false),
                                            ]),
                                    ])->columnSpan(['lg' => 2]),

                                \Filament\Schemas\Components\Group::make()
                                    ->schema([
                                        Section::make(__('resources/proformaInvoice/strings.form.shipment_and_charges'))
                                            ->schema([
                                                static::getAllowPartialShipmentField(),
                                                static::getAllowTransShipmentField(),
                                                static::getDiscountField()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotalAmount($get, $set)),
                                                static::getFreightChargesField()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotalAmount($get, $set)),
                                                static::getOtherChargesField()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotalAmount($get, $set)),
                                                static::getTotalCfrAmountField(),
                                                static::getAttachmentsField(),
                                            ]),
                                    ])->columnSpan(['lg' => 1]),
                            ])->columns(3),

                        Tab::make(__('resources/proformaInvoice/strings.form.tabs.details'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Section::make(__('resources/proformaInvoice/strings.form.additional_details'))
                                    ->schema([
                                        static::getContractNoField(),
                                        static::getBuyerCommCardNumField(),
                                        static::getValidityDateField(),
                                        static::getDeliveryTermsField(),
                                        static::getTransportModeField(),
                                        static::getMainCurrencyIdField(),
                                        static::getSecondaryCurrencyIdField(),
                                        static::getOriginCountryField(),
                                        static::getDestinationCountryField(),
                                        static::getBeneficiaryCountryField(),
                                        static::getPortOfLoadingField(),
                                        static::getPortOfDischargeField(),
                                    ])->columns(4),
                            ]),
                    ])->columnSpan('full'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'creator',
                'updater',
                'attachments',
                'consigneeCompany',
                'items',
                'items.attachments',
                'items.product',
                'mainCurrency',
                'purchaseOrders',
                'purchaseRequests',
                'secondaryCurrency',
                'sellerCompany',
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "📝 " . $record->id;
    }

    public static function getModelLabel(): string
    {
        return __('resources/proformaInvoice/strings.general.model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SmartCacheManager::remember(
            'ProformaInvoice',
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
        return __('resources/dashboard/strings.navigation_group.operational_first');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProformaInvoices::route('/'),
            'create' => CreateProformaInvoice::route('/create'),
            'edit' => EditProformaInvoice::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/proformaInvoice/strings.general.plural_model_label');
    }

    public static function getRelations(): array
    {
        return [
            PurchaseOrdersRelationManager::class,
            PurchaseRequestsRelationManager::class,
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Details')
                    ->tabs([
                        Tab::make('General')
                            ->label(__('resources/proformaInvoice/strings.infolist.tab_general'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make()->schema([
                                    static::viewInvoiceNo(),
                                    static::viewInvoiceDate(),
                                    static::viewValidityDate(),
                                    static::viewSellerCompany(),
                                    static::viewConsigneeCompany(),
                                    static::viewMainCurrency(),
                                    static::viewSecondaryCurrency(),
                                    static::viewContractNo(),
                                    static::viewBuyerCommCardNum(),
                                    static::viewTransportMode(),
                                    static::viewDeliveryTerms(),
                                    static::viewCreator(),
                                    static::viewUpdater(),
                                    static::viewCreatedAt(),
                                    static::viewUpdatedAt(),
                                ])->columns(3),
                            ]),
                        Tab::make('Details')
                            ->label(__('resources/proformaInvoice/strings.infolist.tab_details'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make()->schema([
                                    static::viewBeneficiaryCountry(),
                                    static::viewOriginCountry(),
                                    static::viewDestinationCountry(),
                                    static::viewPortOfLoading(),
                                    static::viewPortOfDischarge(),
                                    static::viewAllowTransShipment(),
                                    static::viewAllowPartialShipment(),
                                    static::viewDiscount(),
                                    static::viewFreightCharges(),
                                    static::viewOtherCharges(),
                                    static::viewTotalCfrAmount(),
                                ])->columns(3),
                            ]),
                        Tab::make('Purchase Requests')
                            ->label(__('resources/proformaInvoice/strings.infolist.tab_purchase_requests'))
                            ->icon('heroicon-o-shopping-cart')
                            ->schema([
                                Section::make()->schema([static::viewPurchaseRequests()])
                            ])
                            ->badge(fn($record) => $record->purchaseRequests->count())
                            ->badgeColor('primary'),
                        Tab::make('Items')
                            ->label(__('resources/proformaInvoice/strings.infolist.tab_items'))
                            ->icon('heroicon-o-list-bullet')
                            ->schema([
                                Section::make()->schema([static::viewInvoiceItems()])
                            ])
                            ->badge(fn($record) => $record->items->count())
                            ->badgeColor('success'),
                        Tab::make('Documents')
                            ->label(__('resources/proformaInvoice/strings.infolist.tab_documents'))
                            ->icon('heroicon-o-paper-clip')
                            ->schema([
                                Section::make()->schema([static::viewAttachments()])
                            ])
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
                static::showPurchaseOrders(),
                static::showInvoiceNo(),
                static::showSellerCompany(),
                static::showConsigneeCompany(),
                static::showTotalAmount(),
                static::showInvoiceDate(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getSellerCompanyFilter(),
                static::getConsigneeCompanyFilter(),
                static::getDeliveryTermsFilter(),
                static::getTransportModeFilter(),
                static::getMainCurrencyFilter(),
                static::getCreatorFilter(),
                static::getUpdaterFilter(),
                static::getTrashedFilter(),
                static::getInvoiceDateFilter(),
                static::getHasPurchaseRequestsFilter(),
                static::getHasPurchaseOrdersFilter(),
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
                        ->exporter(ProformaInvoiceExporter::class),
                ]),
            ])
            ->groups([
                Group::make('sellerCompany.name')
                    ->label(__('resources/proformaInvoice/strings.filters.seller_company'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'sellerCompany')),
                Group::make('consigneeCompany.name')
                    ->label(__('resources/proformaInvoice/strings.filters.consignee_company'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'consigneeCompany')),
            ])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('id', 'desc');
    }

}
