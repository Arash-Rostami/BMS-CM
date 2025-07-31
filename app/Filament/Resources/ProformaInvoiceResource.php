<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Operational\ProformaInvoiceResource\Exports\ProformaInvoiceExporter;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Pages;
use App\Filament\Resources\Operational\ProformaInvoiceResource\RelationManagers;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\Filters as ProformaInvoiceFilters;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\Form as ProformaInvoiceForm;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\Infolist as ProformaInvoiceInfolist;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\Table as ProformaInvoiceTable;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\TotalAmountCalculation;
use App\Models\ProformaInvoice;
use Filament\Forms;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Infolists\Components;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\Tabs as InfoTabs;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProformaInvoiceResource extends Resource
{
    use ProformaInvoiceForm, TotalAmountCalculation, ProformaInvoiceTable, ProformaInvoiceFilters, ProformaInvoiceInfolist;

    protected static ?string $model = ProformaInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Proforma Invoice')
                    ->tabs([
                        Tabs\Tab::make(__('resources/proformaInvoice/strings.form.tabs.general'))
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Section::make(__('resources/proformaInvoice/strings.form.invoice_details'))
                                            ->schema([
                                                static::getPurchaseRequestsField(),
                                                static::getInvoiceNoField(),
                                                static::getInvoiceDateField(),
                                                static::getSellerCompanyIdField(),
                                                static::getConsigneeCompanyIdField(),
                                            ])->columns(2),

                                        Forms\Components\Section::make(__('resources/proformaInvoice/strings.form.invoice_items'))
                                            ->schema([
                                                Forms\Components\Repeater::make('items')
                                                    ->relationship()
                                                    ->schema([
                                                        static::getItemProductIdField(),
                                                        static::getItemQuantityField(),
                                                        static::getItemUnitPriceField(),
                                                        static::getItemHsCodeField(),
                                                        static::getItemOriginField(),
                                                        static::getItemNetWeightField(),
                                                        static::getItemGrossWeightField(),
                                                        static::getItemNotesToggle(),
                                                        static::getItemEnglishNotesToggle(),
                                                        static::getItemTotalAmountField(),
                                                        static::getItemDescriptionField(),
                                                        static::getItemEnglishDescriptionField(),
                                                    ])
                                                    ->columns(4)
                                                    ->live(true)
                                                    ->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => self::updateTotalAmount($get, $set))
                                                    ->deleteAction(fn($action) => $action->after(fn(Forms\Get $get, Forms\Set $set) => self::updateTotalAmount($get, $set)))
                                                    ->addActionLabel(__('resources/proformaInvoice/strings.form.add_invoice_item'))
                                                    ->label(false),
                                            ]),
                                    ])->columnSpan(['lg' => 2]),

                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Section::make(__('resources/proformaInvoice/strings.form.shipment_and_charges'))
                                            ->schema([
                                                static::getAllowPartialShipmentField(),
                                                static::getAllowTransShipmentField(),
                                                static::getDiscountField()
                                                    ->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => self::updateTotalAmount($get, $set)),
                                                static::getFreightChargesField()
                                                    ->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => self::updateTotalAmount($get, $set)),
                                                static::getOtherChargesField()
                                                    ->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => self::updateTotalAmount($get, $set)),
                                                static::getTotalCfrAmountField(),
                                                static::getAttachmentsField(),
                                            ]),
                                    ])->columnSpan(['lg' => 1]),
                            ])->columns(3),

                        Tabs\Tab::make(__('resources/proformaInvoice/strings.form.tabs.details'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Section::make(__('resources/proformaInvoice/strings.form.additional_details'))
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::showID(),
                static::showPurchaseRequests(),
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
                        ->exporter(ProformaInvoiceExporter::class),
                ]),
            ])
            ->groups([
                Group::make('sellerCompany.name')
                    ->label(__('resources/proformaInvoice/strings.filters.seller_company')),
                Group::make('consigneeCompany.name')
                    ->label(__('resources/proformaInvoice/strings.filters.consignee_company')),
            ])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('id', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfoTabs::make('Details')
                    ->tabs([
                        Tab::make('General')
                            ->label(__('resources/proformaInvoice/strings.infolist.tab_general'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Components\Section::make()->schema([
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
                                Components\Section::make()->schema([
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
                                Components\Section::make()->schema([static::viewPurchaseRequests()])
                            ])
                            ->badge(fn ($record) => $record->purchaseRequests->count())
                            ->badgeColor('primary'),
                        Tab::make('Items')
                            ->label(__('resources/proformaInvoice/strings.infolist.tab_items'))
                            ->icon('heroicon-o-list-bullet')
                            ->schema([
                                Components\Section::make()->schema([static::viewInvoiceItems()])
                            ])
                            ->badge(fn ($record) => $record->items->count())
                            ->badgeColor('success'),
                        Tab::make('Documents')
                            ->label(__('resources/proformaInvoice/strings.infolist.tab_documents'))
                            ->icon('heroicon-o-paper-clip')
                            ->schema([
                                Components\Section::make()->schema([static::viewAttachments()])
                            ])
                            ->badge(fn ($record) => $record->attachments->count())
                            ->badgeColor('info'),
                    ])->columnSpanFull(),
            ]);
    }
    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProformaInvoices::route('/'),
            'create' => Pages\CreateProformaInvoice::route('/create'),
            'edit' => Pages\EditProformaInvoice::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/purchaseRequest/strings.general.navigation_group');
    }


    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getModelLabel(): string
    {
        return __('resources/proformaInvoice/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/proformaInvoice/strings.general.plural_model_label');
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
        return "📝 " . $record->id;
    }
}
