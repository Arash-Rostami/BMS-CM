<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\UpdatesFromPurchaseOrders;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\UpdatesFromRegisteredOrders;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\UpdatesFromPurchaseRequests;
use App\Models\Product;
use App\Services\CodeGenerator;
use App\Services\Country;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;

trait Form
{
    use UpdatesFromPurchaseRequests;
    use UpdatesFromPurchaseOrders;
    use UpdatesFromRegisteredOrders;
    use ItemAmountCalculation;

    // Main Form Fields

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

    public static function getBuyerCompanyIdField(): Select
    {
        return Select::make('buyer_id')
            ->label(__('resources/proformaInvoice/strings.form.buyer_company'))
            ->relationship('buyerCompany', 'name')
            ->searchable(['name', 'english_name'])
            ->preload()
            ->required()
            ->different('seller_company_id')
            ->rules(['exists:companies,id'])
            ->validationMessages([
                'required' => __('resources/proformaInvoice/strings.form.validation.required'),
                'exists' => __('resources/proformaInvoice/strings.form.validation.exists'),
                'different' => __('resources/proformaInvoice/strings.form.validation.seller_buyer_different'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.buyer_company'));
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
            ->readOnly()
            ->validationAttribute(__('resources/proformaInvoice/strings.form.freight_charges'));
    }

    public static function getInvoiceDateField(): DatePicker
    {
        return DatePicker::make('invoice_date')
            ->label(__('resources/proformaInvoice/strings.form.invoice_date'))
            ->native(false)
            ->default('today')
            ->required()
            ->rules(['before_or_equal:' . now()->addDay()->toDateString()])
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
            ->readOnly()
            ->default(fn($operation) => $operation == 'create' ? CodeGenerator::generate('invoice_no') : null)
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
            ->hiddenLabel()
            ->maxLength(65535)
            ->reactive()
            ->extraAttributes(fn(Get $get) => ['style' => !$get('show_notes') ? 'display: none;' : ''])
            ->columnSpan('full')
            ->rules(['nullable', 'string', 'max:65535'])
            ->validationMessages([
                'max' => __('resources/proformaInvoice/strings.form.validation.max_string'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.item_description'));
    }

    public static function getItemFreightChargesField(): TextInput
    {
        return TextInput::make('freight_charges')
            ->label(__('resources/proformaInvoice/strings.form.item_freight_charges'))
            ->numeric()
            ->minValue(0)
            ->default(0)
            ->live(onBlur: true)
            ->prefix('💰')
            ->rules(['nullable', 'numeric', 'min:0'])
            ->afterStateUpdated(fn(Get $get, Set $set) => static::itemAfterStateUpdated($get, $set))
            ->validationAttribute(__('resources/proformaInvoice/strings.form.item_freight_charges'));
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
            ->columnSpan(3)
            ->reactive()
            ->default(fn(Get $get) => filled($get('description')))
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
            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
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
            ->afterStateUpdated(fn(Get $get, Set $set) => static::itemAfterStateUpdated($get, $set))
            ->numeric()
            ->minValue(0.01)
            ->rules(['nullable', 'numeric', 'min:0.01'])
            ->validationMessages([
                'numeric' => __('resources/proformaInvoice/strings.form.validation.numeric'),
                'min' => __('resources/proformaInvoice/strings.form.validation.min_numeric'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.quantity'));
    }

    public static function getItemTotalAmountField(): TextInput
    {
        return TextInput::make('total_amount')
            ->label(__('resources/proformaInvoice/strings.form.item_total_amount'))
            ->readOnly()
            ->live()
            ->dehydrateStateUsing(fn($state) => is_string($state) ? (float)str_replace(['💰', ',', ' '], '', $state) : $state)
            ->formatStateUsing(fn(Get $get) => is_numeric($get('total_amount')) ? '💰 ' . number_format($get('total_amount'), 2) : $get('total_amount'));

    }

    public static function getItemUnitField(): Select
    {
        return Select::make('unit')
            ->label(__('resources/proformaInvoice/strings.form.unit'))
            ->options(__('resources/target/strings.metrics'))
            ->required()
            ->columnSpan(1)
            ->validationMessages([
                'required' => __('resources/proformaInvoice/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.unit'));
    }

    public static function getItemUnitPriceField(): TextInput
    {
        return TextInput::make('unit_price')
            ->label(__('resources/proformaInvoice/strings.form.unit_price'))
            ->live(onBlur: true)
            ->afterStateUpdated(fn(Get $get, Set $set) => static::itemAfterStateUpdated($get, $set))
            ->prefix('💰')
            ->numeric()
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

    public static function getNotesField(): RichEditor
    {
        return RichEditor::make('notes')
            ->label(__('resources/proformaInvoice/strings.form.notes'))
            ->toolbarButtons(['bold', 'underline', 'link', 'bulletList', 'orderedList'])
            ->columnSpanFull();
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
            ->label(__('resources/general/strings.relevant_module.form.purchase_orders'))
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
            ->validationAttribute(__('resources/general/strings.relevant_module.form.purchase_orders'));
    }

    public static function getPurchaseRequestsField(): Select
    {
        return Select::make('purchaseRequests')
            ->label(__('resources/general/strings.relevant_module.form.purchase_requests'))
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
            ->validationAttribute(__('resources/general/strings.relevant_module.form.purchase_requests'));
    }

    public static function getRegisteredOrdersField(): Select
    {
        return Select::make('registeredOrders')
            ->label(__('resources/general/strings.relevant_module.form.registered_orders'))
            ->relationship(
                name: 'registeredOrders',
                modifyQueryUsing: fn($query) => $query->whereHas('status', fn($statusQuery) => $statusQuery->whereIn('english_name', ['Submitted']))->latest())
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->formatted_name)
            ->columnSpanFull()
            ->multiple()
            ->preload()
            ->live()
            ->searchable()
            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                self::populateFromRO($state, $set);
                self::updateTotalAmount($get, $set);
            })
            ->visible(fn(Get $get): bool => $get('source_type') === 'ro')
            ->validationAttribute(__('resources/general/strings.relevant_module.form.registered_orders'));
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
        return Select::make('seller_id')
            ->label(__('resources/proformaInvoice/strings.form.seller_company'))
            ->relationship('sellerCompany', 'name')
            ->searchable(['name', 'english_name'])
            ->required()
            ->different('buyer_company_id')
            ->preload()
            ->rules(['exists:companies,id'])
            ->validationMessages([
                'required' => __('resources/proformaInvoice/strings.form.validation.required'),
                'exists' => __('resources/proformaInvoice/strings.form.validation.exists'),
                'different' => __('resources/proformaInvoice/strings.form.validation.seller_buyer_different'),
            ])
            ->validationAttribute(__('resources/proformaInvoice/strings.form.seller_company'));
    }

    public static function getSourceTypeField(): Radio
    {
        return Radio::make('source_type')
            ->label(__('resources/general/strings.relevant_module.form.related_to'))
            ->inline()
            ->options([
                'pr' => __('resources/general/strings.relevant_module.form.purchase_requests_related'),
                'ro' => __('resources/general/strings.relevant_module.form.registered_orders_related'),
                'po' => __('resources/general/strings.relevant_module.form.purchase_orders_related'),
            ])
            ->columnSpanFull()
            ->default(null)
            ->live();
    }

    public static function getTotalAmountField(): TextEntry
    {
        return TextEntry::make('total_amount')
            ->label(__('resources/proformaInvoice/strings.form.total_amount'))
            ->formatStateUsing(fn(Get $get) => is_numeric($get('total_amount')) ? '💰 ' . number_format($get('total_amount'), 2) : $get('total_amount'));
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
