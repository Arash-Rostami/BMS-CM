<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\UpdatesFromPurchaseOrders;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\UpdatesFromPurchaseRequests;
use App\Models\Product;
use App\Services\CodeGenerator;
use App\Services\Country;
use App\Services\FileUploadManager;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

trait Form
{
    use UpdatesFromPurchaseRequests, UpdatesFromPurchaseOrders;

    // Main Form Fields

    public static function getAllowPartialShipmentField(): Toggle
    {
        return Toggle::make('allow_partial_shipment')
            ->label(__('resources/proformaInvoice/strings.form.allow_partial_shipment'))
            ->onIcon('heroicon-s-check-circle')
            ->offIcon('heroicon-s-x-circle');
    }

    public static function getAllowTransShipmentField(): Toggle
    {
        return Toggle::make('allow_trans_shipment')
            ->label(__('resources/proformaInvoice/strings.form.allow_trans_shipment'))
            ->onIcon('heroicon-s-check-circle')
            ->offIcon('heroicon-s-x-circle');
    }

    public static function getAttachmentsField(): FileUpload
    {
        return FileUpload::make('attachments')
            ->label(__('resources/proformaInvoice/strings.form.attachments'))
            ->multiple()
            ->disk('public')
            ->visibility('public')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'application/pdf', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',])
            ->maxSize(2500)
            ->previewable()
            ->openable()
            ->downloadable()
            ->hintIconTooltip(fn($record) => $record?->attachments()->latest('id')->implode('name', "\n") ?? '')
            ->validationMessages([
                'accepted' => __('resources/proformaInvoice/strings.form.validation.attachments_type'),
                'max_size' => __('resources/proformaInvoice/strings.form.validation.attachments_size'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.attachments'))
            ->saveUploadedFileUsing(static function (UploadedFile $file, $state) {
                return app(FileUploadManager::class)->storeTemporary($file);
            })
            ->saveRelationshipsUsing(static function ($record, array $state, Set $set) {
                if ($record) {
                    app(FileUploadManager::class)
                        ->processTemporaryFiles($record, $state)
                        ->refreshComponent($record, $set);
                }
            })
            ->afterStateHydrated(static function (FileUpload $component, $state, $record) {
                $component->state(
                    $record?->attachments
                        ? $record->attachments->pluck('path')->toArray()
                        : []
                );
            });
    }

    public static function getBeneficiaryCountryField(): Select
    {
        return Select::make('beneficiary_country')
            ->label(__('resources/proformaInvoice/strings.form.beneficiary_country'))
            ->options(fn() => (new Country())->getCountriesList())
            ->searchable()
            ->validationAttribute(__('resources/proformaInvoice/strings.form.beneficiary_country'));
    }

    public static function getBuyerCommCardNumField(): TextInput
    {
        return TextInput::make('buyer_comm_card_num')
            ->label(__('resources/proformaInvoice/strings.form.buyer_comm_card_num'))
            ->maxLength(255)
            ->rules(['nullable', 'max:255'])
            ->validationMessages([
                'max' => __('resources/proformaInvoice/strings.form.validation.max_string'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.buyer_comm_card_num'));
    }

    public static function getConsigneeCompanyIdField(): Select
    {
        return Select::make('consignee_company_id')
            ->label(__('resources/proformaInvoice/strings.form.consignee_company'))
            ->relationship('consigneeCompany', 'name')
            ->searchable(['name', 'english_name'])
            ->preload()
            ->required()
            ->different('seller_company_id')
            ->rules(['exists:companies,id'])
            ->validationMessages([
                'required' => __('resources/proformaInvoice/strings.form.validation.required'),
                'exists' => __('resources/proformaInvoice/strings.form.validation.exists'),
                'different' => __('resources/proformaInvoice/strings.form.validation.seller_consignee_different'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.consignee_company'));
    }

    public static function getContractNoField(): TextInput
    {
        return TextInput::make('contract_no')
            ->label(__('resources/proformaInvoice/strings.form.contract_no'))
            ->maxLength(255)
            ->rules(['nullable', 'max:255'])
            ->validationMessages([
                'max' => __('resources/proformaInvoice/strings.form.validation.max_string'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.contract_no'));
    }

    public static function getDeliveryTermsField(): Select
    {
        return Select::make('delivery_terms')
            ->label(__('resources/proformaInvoice/strings.form.delivery_terms'))
            ->options(__('resources/proformaInvoice/strings.general.delivery_terms'))
            ->validationAttribute(__('resources/proformaInvoice/strings.form.delivery_terms'));
    }

    public static function getDestinationCountryField(): Select
    {
        return Select::make('destination_country')
            ->label(__('resources/proformaInvoice/strings.form.destination_country'))
            ->options(fn() => (new Country())->getCountriesList())
            ->searchable()
            ->validationAttribute(__('resources/proformaInvoice/strings.form.destination_country'));
    }

    //  "Details" Tab

    public static function getDiscountField(): TextInput
    {
        return TextInput::make('discount')
            ->label(__('resources/proformaInvoice/strings.form.discount'))
            ->numeric()
            ->live(onBlur: true)
            ->prefix('💰')
            ->minValue(0)
            ->rules(['nullable', 'numeric', 'min:0'])
            ->validationMessages([
                'numeric' => __('resources/proformaInvoice/strings.form.validation.numeric'),
                'min' => __('resources/proformaInvoice/strings.form.validation.min_numeric'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.discount'));
    }

    public static function getFreightChargesField(): TextInput
    {
        return TextInput::make('freight_charges')
            ->label(__('resources/proformaInvoice/strings.form.freight_charges'))
            ->numeric()
            ->minValue(0)
            ->live(onBlur: true)
            ->prefix('💰')
            ->rules(['nullable', 'numeric', 'min:0'])
            ->validationMessages([
                'numeric' => __('resources/proformaInvoice/strings.form.validation.numeric'),
                'min' => __('resources/proformaInvoice/strings.form.validation.min_numeric'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.freight_charges'));
    }

    public static function getInvoiceDateField(): DatePicker
    {
        return DatePicker::make('invoice_date')
            ->label(__('resources/proformaInvoice/strings.form.invoice_date'))
            ->native(false)
            ->default('today')
            ->required()
            ->rules(['before_or_equal:today'])
            ->validationMessages([
                'required' => __('resources/proformaInvoice/strings.form.validation.required'),
                'before_or_equal' => __('resources/proformaInvoice/strings.form.validation.before_or_equal_today'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.invoice_date'));
    }

    public static function getInvoiceNoField(): TextInput
    {
        return TextInput::make('invoice_no')
            ->label(__('resources/proformaInvoice/strings.form.invoice_no'))
            ->required()
            ->default(fn($operation) => $operation == 'create' ? CodeGenerator::generate() : null)
            ->unique(ignoreRecord: true)
            ->maxLength(255)
            ->validationMessages([
                'max' => __('resources/proformaInvoice/strings.form.validation.max_string'),
                'required' => __('resources/proformaInvoice/strings.form.validation.required'),
                'unique' => __('resources/proformaInvoice/strings.form.validation.unique'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.invoice_no'));
    }

    public static function getItemDescriptionField(): Textarea
    {
        return Textarea::make('description')
            ->label('')
            ->maxLength(65535)
            ->reactive()
            ->extraAttributes(fn(Get $get) => ['style' => !$get('show_notes') ? 'display: none;' : ''])
            ->columnSpan('full')
            ->placeholder('صرفا به فارسی')
            ->rules(['nullable', 'string', 'max:65535'])
            ->validationMessages([
                'max' => __('resources/proformaInvoice/strings.form.validation.max_string'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.item_description'));
    }

    public static function getItemEnglishDescriptionField(): Textarea
    {
        return Textarea::make('english_description')
            ->label('')
            ->maxLength(65535)
            ->reactive()
            ->extraAttributes(fn(Get $get) => ['style' => !$get('show_english_notes') ? 'display: none;' : ''])
            ->columnSpan('full')
            ->placeholder('Only In English')
            ->rules(['nullable', 'string', 'max:65535'])
            ->validationMessages([
                'max' => __('resources/proformaInvoice/strings.form.validation.max_string'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.item_english_description'));
    }

    public static function getItemEnglishNotesToggle(): Toggle
    {
        return Toggle::make('show_english_notes')
            ->label(__('resources/proformaInvoice/strings.form.add_english_notes'))
            ->onIcon('heroicon-s-check-circle')
            ->offIcon('heroicon-s-x-circle')
            ->onColor('success')
            ->offColor('danger')
            ->columnSpan(2)
            ->reactive()
            ->afterStateHydrated(fn(Set $set, Get $get) => $set('show_english_notes', filled($get('english_description'))))
            ->afterStateUpdated(fn(?bool $state, Set $set) => !$state ? $set('english_description', null) : null);
    }

    public static function getItemGrossWeightField(): TextInput
    {
        return TextInput::make('gross_weight')
            ->label(__('resources/proformaInvoice/strings.form.gross_weight'))
            ->numeric()
            ->minValue(0)
            ->prefix('⏲️')
            ->rules(['nullable', 'numeric', 'min:0'])
            ->validationMessages([
                'numeric' => __('resources/proformaInvoice/strings.form.validation.numeric'),
                'min' => __('resources/proformaInvoice/strings.form.validation.min_numeric'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.gross_weight'));
    }

    public static function getItemHsCodeField(): TextInput
    {
        return TextInput::make('hs_code')
            ->label(__('resources/proformaInvoice/strings.form.hs_code'))
            ->maxLength(255)
            ->rules(['nullable', 'max:255'])
            ->validationMessages([
                'max' => __('resources/proformaInvoice/strings.form.validation.max_string'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.hs_code'));
    }

    public static function getItemNetWeightField(): TextInput
    {
        return TextInput::make('net_weight')
            ->label(__('resources/proformaInvoice/strings.form.net_weight'))
            ->numeric()
            ->minValue(0)
            ->prefix('⏲️')
            ->rules(['nullable', 'numeric', 'min:0'])
            ->validationMessages([
                'numeric' => __('resources/proformaInvoice/strings.form.validation.numeric'),
                'min' => __('resources/proformaInvoice/strings.form.validation.min_numeric'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.net_weight'));
    }

    public static function getItemNotesToggle(): Toggle
    {
        return Toggle::make('show_notes')
            ->label(__('resources/proformaInvoice/strings.form.add_notes'))
            ->onIcon('heroicon-s-check-circle')
            ->offIcon('heroicon-s-x-circle')
            ->onColor('success')
            ->offColor('danger')
            ->columnSpan(1)
            ->reactive()
            ->afterStateHydrated(fn(Set $set, Get $get) => $set('show_notes', filled($get('description'))))
            ->afterStateUpdated(fn(?bool $state, Set $set) => !$state ? $set('description', null) : null);
    }

    public static function getItemOriginField(): Select
    {
        return Select::make('origin')
            ->label(__('resources/proformaInvoice/strings.form.origin'))
            ->options(fn() => (new Country())->getCountriesList())
            ->placeholder('')
            ->searchable()
            ->validationAttribute(__('resources/proformaInvoice/strings.form.origin'));
    }

    public static function getItemProductIdField(): Select
    {
        return Select::make('product_id')
            ->label(__('resources/proformaInvoice/strings.form.product'))
            ->relationship('product', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->getCustomizedLabelAttribute() ?? $record->slug ?? '-')
            ->searchable(['name', 'english_name', 'code'])
            ->preload()
            ->reactive()
            ->columnSpan(2)
            ->required()
            ->afterStateUpdated(fn($state, Set $set) => $set('hs_code', Product::find($state)?->specifications?->first()?->hs_code))
            ->rules(['exists:products,id'])
            ->validationMessages([
                'required' => __('resources/proformaInvoice/strings.form.validation.required'),
                'exists' => __('resources/proformaInvoice/strings.form.validation.exists'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.product'));
    }

    public static function getItemQuantityField(): TextInput
    {
        return TextInput::make('quantity')
            ->label(__('resources/proformaInvoice/strings.form.quantity'))
            ->live(onBlur: true)
            ->afterStateUpdated(function (Get $get, Set $set) {
                $quantity = floatval($get('quantity') ?? 0);
                $unitPrice = floatval($get('unit_price') ?? 0);
                $set('total_amount', number_format($quantity * $unitPrice, 2, '.', ''));
            })
            ->numeric()
            ->live()
            ->minValue(0.01)
            ->rules(['nullable', 'numeric', 'min:0.01'])
            ->validationMessages([
                'numeric' => __('resources/proformaInvoice/strings.form.validation.numeric'),
                'min' => __('resources/proformaInvoice/strings.form.validation.min_numeric'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.quantity'));
    }

    public static function getItemTotalAmountField(): TextEntry
    {
        return TextEntry::make('total_amount')
            ->label(__('resources/proformaInvoice/strings.form.item_total_amount'))
            ->live()
            ->formatStateUsing(fn(Get $get) => is_numeric($get('total_amount')) ? '💰 ' . number_format($get('total_amount'), 2) : $get('total_amount'));

    }

    public static function getItemUnitPriceField(): TextInput
    {
        return TextInput::make('unit_price')
            ->label(__('resources/proformaInvoice/strings.form.unit_price'))
            ->live(onBlur: true)
            ->afterStateUpdated(function (Get $get, Set $set) {
                $quantity = floatval($get('quantity') ?? 0);
                $unitPrice = floatval($get('unit_price') ?? 0);
                $set('total_amount', number_format($quantity * $unitPrice, 2, '.', ''));
            })
            ->prefix('💰')
            ->numeric()
            ->live()
            ->minValue(0)
            ->rules(['nullable', 'numeric', 'min:0'])
            ->validationMessages([
                'numeric' => __('resources/proformaInvoice/strings.form.validation.numeric'),
                'min' => __('resources/proformaInvoice/strings.form.validation.min_numeric'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.unit_price'));
    }


    // Invoice Item Fields (for Repeater)

    public static function getMainCurrencyIdField(): Select
    {
        return Select::make('main_currency_id')
            ->label(__('resources/proformaInvoice/strings.form.main_currency'))
            ->relationship('mainCurrency', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->searchable()
            ->required()
            ->preload()
            ->different('secondary_currency_id')
            ->rules(['nullable', 'exists:currencies,id'])
            ->validationMessages([
                'required' => __('resources/proformaInvoice/strings.form.validation.required'),
                'exists' => __('resources/proformaInvoice/strings.form.validation.exists'),
                'different' => __('resources/proformaInvoice/strings.form.validation.currency_different'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.main_currency'));
    }

    public static function getOriginCountryField(): Select
    {
        return Select::make('origin_country')
            ->label(__('resources/proformaInvoice/strings.form.origin_country'))
            ->options(fn() => (new Country())->getCountriesList())
            ->searchable()
            ->validationAttribute(__('resources/proformaInvoice/strings.form.origin_country'));
    }

    public static function getOtherChargesField(): TextInput
    {
        return TextInput::make('other_charges')
            ->label(__('resources/proformaInvoice/strings.form.other_charges'))
            ->numeric()
            ->minValue(0)
            ->live(onBlur: true)
            ->prefix('💰')
            ->rules(['nullable', 'numeric', 'min:0'])
            ->validationMessages([
                'numeric' => __('resources/proformaInvoice/strings.form.validation.numeric'),
                'min' => __('resources/proformaInvoice/strings.form.validation.min_numeric'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.other_charges'));
    }

    public static function getPortOfDischargeField(): TextInput
    {
        return TextInput::make('port_of_discharge')
            ->label(__('resources/proformaInvoice/strings.form.port_of_discharge'))
            ->maxLength(255)
            ->rules(['nullable', 'max:255'])
            ->validationMessages([
                'max' => __('resources/proformaInvoice/strings.form.validation.max_string'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.port_of_discharge'));
    }

    public static function getPortOfLoadingField(): TextInput
    {
        return TextInput::make('port_of_loading')
            ->label(__('resources/proformaInvoice/strings.form.port_of_loading'))
            ->maxLength(255)
            ->rules(['nullable', 'max:255'])
            ->validationMessages([
                'max' => __('resources/proformaInvoice/strings.form.validation.max_string'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.port_of_loading'));
    }

    public static function getPurchaseOrdersField(): Select
    {
        return Select::make('purchaseOrders')
            ->label(__('resources/proformaInvoice/strings.form.purchase_orders'))
            ->relationship(
                name: 'purchaseOrders',
                modifyQueryUsing: fn($query) => $query->whereHas('status', fn($statusQuery) => $statusQuery->whereIn('english_name', ['Approved']))->latest())
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->formatted_name)
            ->columnSpanFull()
            ->multiple()
            ->preload()
            ->live()
            ->searchable()
            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                self::populateFromPO($state, $set);
                self::updateTotalAmount($get, $set);
            })
            ->visible(fn(Get $get): bool => $get('source_type') === 'po')
            ->validationAttribute(__('resources/proformaInvoice/strings.form.purchase_orders'));
    }

    public static function getPurchaseRequestsField(): Select
    {
        return Select::make('purchaseRequests')
            ->label(__('resources/proformaInvoice/strings.form.purchase_requests'))
            ->relationship(
                name: 'purchaseRequests',
                modifyQueryUsing: fn($query) => $query->whereHas('status', fn($statusQuery) => $statusQuery->whereIn('english_name', ['Authorized', 'Conditional']))->latest())
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->formatted_name)
            ->columnSpanFull()
            ->multiple()
            ->preload()
            ->live()
            ->searchable()
            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                self::populateFromPR($state, $set);
                self::updateTotalAmount($get, $set);
            })
            ->visible(fn(Get $get): bool => $get('source_type') === 'pr')
            ->validationAttribute(__('resources/proformaInvoice/strings.form.purchase_requests'));
    }

    public static function getSecondaryCurrencyIdField(): Select
    {
        return Select::make('secondary_currency_id')
            ->label(__('resources/proformaInvoice/strings.form.secondary_currency'))
            ->relationship('secondaryCurrency', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->searchable()
            ->preload()
            ->rules(['nullable', 'exists:currencies,id'])
            ->validationMessages([
                'exists' => __('resources/proformaInvoice/strings.form.validation.exists'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.secondary_currency'));
    }

    public static function getSellerCompanyIdField(): Select
    {
        return Select::make('seller_company_id')
            ->label(__('resources/proformaInvoice/strings.form.seller_company'))
            ->relationship('sellerCompany', 'name')
            ->searchable(['name', 'english_name'])
            ->required()
            ->different('consignee_company_id')
            ->preload()
            ->rules(['exists:companies,id'])
            ->validationMessages([
                'required' => __('resources/proformaInvoice/strings.form.validation.required'),
                'exists' => __('resources/proformaInvoice/strings.form.validation.exists'),
                'different' => __('resources/proformaInvoice/strings.form.validation.seller_consignee_different'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.seller_company'));
    }

    public static function getSourceTypeField(): Radio
    {
        return Radio::make('source_type')
            ->label(__('resources/proformaInvoice/strings.form.related_to'))
            ->inline()
            ->options([
                'po' => __('resources/proformaInvoice/strings.form.purchase_orders_related'),
                'pr' => __('resources/proformaInvoice/strings.form.purchase_requests_related')
            ])
            ->default('po')
            ->live();
    }

    public static function getTotalCfrAmountField(): TextEntry
    {
        return TextEntry::make('total_cfr_amount')
            ->label(__('resources/proformaInvoice/strings.form.total_cfr_amount'))
            ->formatStateUsing(fn(Get $get) => is_numeric($get('total_cfr_amount')) ? '💰 ' . number_format($get('total_cfr_amount'), 2) : $get('total_cfr_amount'));
    }

    public static function getTransportModeField(): Select
    {
        return Select::make('transport_mode')
            ->label(__('resources/proformaInvoice/strings.form.transport_mode'))
            ->options(__('resources/proformaInvoice/strings.general.transport_modes'))
            ->validationAttribute(__('resources/proformaInvoice/strings.form.transport_mode'));
    }

    public static function getValidityDateField(): DatePicker
    {
        return DatePicker::make('validity_date')
            ->label(__('resources/proformaInvoice/strings.form.validity_date'))
            ->native(false)
            ->default(fn(): string => now()->addWeek()->toDateString())
            ->afterOrEqual('invoice_date')
            ->rules(['nullable'])
            ->validationMessages([
                'after_or_equal' => __('resources/proformaInvoice/strings.form.validation.validity_date_after'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.validity_date'));
    }
}
