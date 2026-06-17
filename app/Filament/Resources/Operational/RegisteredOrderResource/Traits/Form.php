<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Traits;

use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\UpdatesFromProformaInvoice;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\UpdatesFromPurchaseOrders;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\UpdatesFromPurchaseRequests;
use App\Models\RegisteredOrder;
use App\Models\Status;
use App\Services\CodeGenerator;
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
    use TotalCalculation,
        ItemCalculation,
        UpdatesFromPurchaseRequests,
        UpdatesFromPurchaseOrders,
        UpdatesFromProformaInvoice;

    public static function getBuyerField(): Select
    {
        return Select::make('buyer_id')
            ->label(__('resources/registeredOrder/strings.form.buyer'))
            ->relationship(
                name: 'buyerCompany',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable(['name', 'english_name'])
            ->preload()
            ->required()
            ->different('seller_id')
            ->validationMessages([
                'required' => __('resources/registeredOrder/strings.form.validation_required'),
                'different' => __('resources/registeredOrder/strings.form.validation_seller_buyer_different'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.buyer'));
    }

    public static function getContractNumberField(): TextInput
    {
        return TextInput::make('contract_no')
            ->label(__('resources/registeredOrder/strings.form.contract_number'))
            ->maxLength(255)
            ->required()
            ->default(fn($operation) => $operation == 'create' ? CodeGenerator::generate('contract_no') : null)
            ->validationMessages([
                'required' => __('resources/registeredOrder/strings.form.validation_required'),
                'max' => __('resources/registeredOrder/strings.form.validation_max_string'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.contract_number'));
    }

    public static function getCurrencyField(): Select
    {
        return Select::make('currency_id')
            ->label(__('resources/registeredOrder/strings.form.currency'))
            ->relationship(
                name: 'currency',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable()
            ->preload()
            ->required()
            ->validationMessages([
                'required' => __('resources/registeredOrder/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.currency'));
    }

    public static function getCurrencyTypeField(): Select
    {
        return Select::make('currency_type')
            ->label(__('resources/registeredOrder/strings.form.currency_type'))
            ->options(__('resources/registeredOrder/strings.general.currency_types'))
            ->validationAttribute(__('resources/registeredOrder/strings.form.currency_type'));
    }

    public static function getExpectedDeliveryDateField()
    {
        return DatePicker::make('expected_delivery_date')
            ->label(__('resources/registeredOrder/strings.form.expected_delivery_date'))
            ->native(false)
            ->jalali()
            ->afterOrEqual('order_date')
            ->validationMessages([
                'after_or_equal' => __('resources/registeredOrder/strings.form.validation_after_or_equal_order_date'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.expected_delivery_date'));
    }

    public static function getIncotermsField(): Select
    {
        return Select::make('incoterms')
            ->label(__('resources/registeredOrder/strings.form.incoterms'))
            ->options(__('resources/proformaInvoice/strings.general.delivery_terms'))
            ->searchable()
            ->validationAttribute(__('resources/registeredOrder/strings.form.incoterms'));
    }

    public static function getInsuranceDateField()
    {
        return DatePicker::make('insurance_date')
            ->label(__('resources/registeredOrder/strings.form.insurance_date'))
            ->jalali()
            ->native(false)
            ->validationAttribute(__('resources/registeredOrder/strings.form.insurance_date'));
    }

    public static function getInsuranceNumberField(): TextInput
    {
        return TextInput::make('insurance_number')
            ->label(__('resources/registeredOrder/strings.form.insurance_number'))
            ->maxLength(255)
            ->validationAttribute(__('resources/registeredOrder/strings.form.insurance_number'));
    }

    public static function getInsuranceProviderField(): TextInput
    {
        return TextInput::make('insurance_provider')
            ->label(__('resources/registeredOrder/strings.form.insurance_provider'))
            ->maxLength(255)
            ->validationAttribute(__('resources/registeredOrder/strings.form.insurance_provider'));
    }

    public static function getItemDescriptionField(): Textarea
    {
        return Textarea::make('description')
            ->hiddenLabel()
            ->maxLength(65535)
            ->reactive()
            ->extraAttributes(fn(Get $get) => ['style' => !$get('show_notes') ? 'display: none;' : ''])
            ->columnSpanFull()
            ->rules(['nullable', 'string', 'max:65535'])
            ->validationMessages([
                'max' => __('resources/registeredOrder/strings.form.validation_max_string'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.item_description'));
    }

    public static function getItemNotesToggle(): Toggle
    {
        return Toggle::make('show_notes')
            ->label(__('resources/registeredOrder/strings.form.add_notes'))
            ->onIcon('heroicon-s-check-circle')
            ->offIcon('heroicon-s-x-circle')
            ->onColor('success')
            ->offColor('danger')
            ->columnSpanFull()
            ->reactive()
            ->afterStateHydrated(fn(Set $set, Get $get) => $set('show_notes', filled($get('description'))))
            ->afterStateUpdated(fn(?bool $state, Set $set) => !$state ? $set('description', null) : null);
    }

    public static function getItemPackingDetailsField(): Textarea
    {
        return Textarea::make('packing_details')
            ->label(__('resources/registeredOrder/strings.form.packing_details'))
            ->rows(2)
            ->columnSpanFull()
            ->maxLength(65535)
            ->validationMessages(['max' => __('resources/registeredOrder/strings.form.validation_max_text')])
            ->validationAttribute(__('resources/registeredOrder/strings.form.packing_details'));
    }

    public static function getNotesField(): RichEditor
    {
        return RichEditor::make('notes')
            ->label(__('resources/registeredOrder/strings.form.notes'))
            ->toolbarButtons(['bold', 'underline', 'link', 'bulletList', 'orderedList'])
            ->columnSpanFull();
    }

    public static function getOrderDateField()
    {
        return DatePicker::make('order_date')
            ->label(__('resources/registeredOrder/strings.form.order_date'))
            ->default(now())
            ->required()
            ->jalali()
            ->native(false)
            ->validationMessages([
                'required' => __('resources/registeredOrder/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.order_date'));
    }

    public static function getProformaInvoicesField(): Select
    {
        return Select::make('proformaInvoices')
            ->label(__('resources/registeredOrder/strings.form.proforma_invoices'))
            ->relationship(name: 'proformaInvoices')
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->formatted_name)
            ->columnSpanFull()
            ->multiple()
            ->preload()
            ->live()
            ->searchable()
            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                self::populateFromPI($state, $set);
                self::updateTotal($get, $set);
            })
            ->visible(fn(Get $get): bool => $get('source_type') === 'pi')
            ->validationAttribute(__('resources/proformaInvoice/strings.form.proforma_invoices'));
    }

    public static function getPurchaseOrdersField(): Select
    {
        return Select::make('purchaseOrders')
            ->label(__('resources/registeredOrder/strings.form.purchase_orders'))
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
                self::updateTotal($get, $set);
            })
            ->visible(fn(Get $get): bool => $get('source_type') === 'po')
            ->validationAttribute(__('resources/proformaInvoice/strings.form.purchase_orders'));
    }

    public static function getPurchaseRequestsField(): Select
    {
        return Select::make('purchaseRequests')
            ->label(__('resources/registeredOrder/strings.form.purchase_requests'))
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
                static::updateTotal($get, $set);
            })
            ->visible(fn(Get $get): bool => $get('source_type') === 'pr')
            ->validationAttribute(__('resources/proformaInvoice/strings.form.purchase_requests'));
    }

    public static function getRoNumberField(): TextInput
    {
        return TextInput::make('ro_number')
            ->label(__('resources/registeredOrder/strings.form.ro_number'))
            ->required()
            ->readOnly()
            ->maxLength(255)
            ->unique(ignoreRecord: true)
            ->default(fn($operation) => $operation == 'create' ? CodeGenerator::generate('ro_number') : null)
            ->validationMessages([
                'required' => __('resources/registeredOrder/strings.form.validation_required'),
                'unique' => __('resources/registeredOrder/strings.form.validation_unique'),
                'max' => __('resources/registeredOrder/strings.form.validation_max_string'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.ro_number'));
    }

    public static function getSellerField(): Select
    {
        return Select::make('seller_id')
            ->label(__('resources/registeredOrder/strings.form.seller'))
            ->relationship(
                name: 'sellerCompany',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable(['name', 'english_name'])
            ->preload()
            ->required()
            ->different('buyer_id')
            ->validationMessages([
                'required' => __('resources/registeredOrder/strings.form.validation_required'),
                'different' => __('resources/registeredOrder/strings.form.validation_seller_buyer_different'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.seller'));
    }

    public static function getSourceTypeField(): Radio
    {
        return Radio::make('source_type')
            ->label(__('resources/proformaInvoice/strings.form.related_to'))
            ->inline()
            ->options([
                'pr' => __('resources/registeredOrder/strings.form.purchase_requests_related'),
                'pi' => __('resources/registeredOrder/strings.form.proforma_invoices_related'),
                'po' => __('resources/registeredOrder/strings.form.purchase_orders_related'),
            ])
            ->columnSpanFull()
            ->default(null)
            ->live();
    }

    public static function getOfficialRegistrationNoField(): TextInput
    {
        return TextInput::make('official_registration_no')
            ->label(__('resources/registeredOrder/strings.form.official_registration_no'))
            ->maxLength(255)
            ->validationAttribute(__('resources/registeredOrder/strings.form.official_registration_no'));
    }

    public static function getStatusField(): Select
    {
        return Select::make('status_id')
            ->label(__('resources/registeredOrder/strings.form.status'))
            ->relationship(
                name: 'status',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
                modifyQueryUsing: fn($query) => $query->where('english_type', RegisteredOrder::TYPE_REGISTERED_ORDER)
            )
            ->default(fn($operation): ?int => $operation === 'create' ? Status::findBy('Registered Order Status', 'Submitted')?->id : null)
            ->searchable()
            ->preload()
            ->required()
            ->validationMessages([
                'required' => __('resources/registeredOrder/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.status'));
    }

    public static function getTotalAmountField(): TextEntry
    {
        return TextEntry::make('total_amount')
            ->label(__('resources/registeredOrder/strings.form.total_amount'))
            ->columnSpan(1)
            ->live()
            ->formatStateUsing(fn(Get $get) => is_numeric($get('total_amount')) ? '💰 ' . number_format($get('total_amount'), 2) : $get('total_amount'));
    }

    public static function getTotalQuantityField(): TextEntry
    {
        return TextEntry::make('total_quantity')
            ->label(__('resources/registeredOrder/strings.form.total_quantity'))
            ->columnSpan(1)
            ->live()
            ->formatStateUsing(fn(Get $get) => is_numeric($get('total_quantity')) ? '📦 ' . number_format($get('total_quantity'), 2) : $get('total_quantity'));
    }

    public static function getValidityDateField()
    {
        return DatePicker::make('validity_date')
            ->label(__('resources/registeredOrder/strings.form.validity_date'))
            ->native(false)
            ->jalali()
            ->afterOrEqual('order_date')
            ->validationMessages([
                'after_or_equal' => __('resources/registeredOrder/strings.form.validation_after_or_equal_order_date'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.validity_date'));
    }

    protected static function getItemEntranceFeeField(): TextInput
    {
        return TextInput::make('entrance_fee')
            ->label(__('resources/registeredOrder/strings.form.entrance_fee'))
            ->numeric()
            ->hint(fn(Get $get) => delimiter($get('entrance_fee')))
            ->minValue(0)
            ->columnSpan(4)
            ->live(onBlur: true)
            ->tooltip(fn (Get $get) => is_numeric($get('entrance_fee')) ? number_format($get('entrance_fee'), 2) : '')
            ->validationMessages(['numeric', 'min'])
            ->validationAttribute(__('resources/registeredOrder/strings.form.entrance_fee'));
    }

    protected static function getItemExtraCostField(): TextInput
    {
        return TextInput::make('extra_cost')
            ->label(__('resources/registeredOrder/strings.form.extra_cost'))
            ->numeric()
            ->afterStateUpdated(fn(Get $get, Set $set) => static::updateItemLineTotal($get, $set))
            ->hint(fn(Get $get) => delimiter($get('extra_cost')))
            ->minValue(0)
            ->columnSpan(4)
            ->live(onBlur: true)
            ->tooltip(fn (Get $get) => is_numeric($get('extra_cost')) ? number_format($get('extra_cost'), 2) : '')
            ->validationMessages(['numeric', 'min'])
            ->validationAttribute(__('resources/registeredOrder/strings.form.extra_cost'));
    }

    protected static function getItemGrossWeightField(): TextInput
    {
        return TextInput::make('gross_weight')
            ->label(__('resources/registeredOrder/strings.form.gross_weight'))
            ->prefix('⏲️')
            ->numeric()
            ->hint(fn(Get $get) => delimiter($get('gross_weight')))
            ->minValue(0)
            ->columnSpan(4)
            ->tooltip(fn (Get $get) => is_numeric($get('gross_weight')) ? number_format($get('gross_weight'), 2) : '')
            ->validationMessages([
                'numeric' => __('resources/registeredOrder/strings.form.validation_numeric'),
                'min' => __('resources/registeredOrder/strings.form.validation_min_numeric_zero'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.gross_weight'));
    }

    protected static function getItemLineTotalField(): TextInput
    {
        return TextInput::make('line_total')
            ->label(__('resources/registeredOrder/strings.form.line_total'))
            ->readOnly()
            ->columnSpan(4)
            ->live()
            ->dehydrateStateUsing(fn($state) => is_numeric($state) ? $state : (float)preg_replace('/[^0-9.]/', '', $state))
            ->formatStateUsing(fn(Get $get) => is_numeric($get('line_total')) ? '💰 ' . number_format($get('line_total'), 2) : $get('line_total'));
    }

    protected static function getItemNetWeightField(): TextInput
    {
        return TextInput::make('net_weight')
            ->label(__('resources/registeredOrder/strings.form.net_weight'))
            ->prefix('⏲️')
            ->numeric()
            ->hint(fn(Get $get) => delimiter($get('net_weight')))
            ->columnSpan(4)
            ->minValue(0)
            ->tooltip(fn (Get $get) => is_numeric($get('net_weight')) ? number_format($get('net_weight'), 2) : '')
            ->validationMessages([
                'numeric' => __('resources/registeredOrder/strings.form.validation_numeric'),
                'min' => __('resources/registeredOrder/strings.form.validation_min_numeric_zero'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.net_weight'));
    }

    protected static function getItemProductIdField(): Select
    {
        return Select::make('product_id')
            ->label(__('resources/registeredOrder/strings.form.product'))
            ->relationship('product', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->searchable(['name', 'english_name', 'code'])
            ->preload()
            ->required()
            ->distinct()
            ->columnSpan(9)
            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
            ->validationMessages([
                'required' => __('resources/registeredOrder/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.product'));
    }

    protected static function getItemQuantityField(): TextInput
    {
        return TextInput::make('quantity')
            ->label(__('resources/registeredOrder/strings.form.quantity'))
            ->required()
            ->numeric()
            ->minValue(0.01)
            ->live(onBlur: true)
            ->columnSpan(3)
            ->tooltip(fn (Get $get) => is_numeric($get('quantity')) ? number_format($get('quantity'), 2) : '')
            ->afterStateUpdated(function (Get $get, Set $set) { static::updateItemLineTotal($get, $set); })
            ->validationMessages([
                'required' => __('resources/registeredOrder/strings.form.validation_required'),
                'numeric' => __('resources/registeredOrder/strings.form.validation_numeric'),
                'min' => __('resources/registeredOrder/strings.form.validation_min_numeric'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.quantity'));
    }

    protected static function getItemShippingCostField(): TextInput
    {
        return TextInput::make('shipping_cost')
            ->label(__('resources/registeredOrder/strings.form.shipping_cost'))
            ->numeric()
            ->hint(fn(Get $get) => delimiter($get('shipping_cost')))
            ->afterStateUpdated(fn(Get $get, Set $set) => static::updateItemLineTotal($get, $set))
            ->minValue(0)
            ->columnSpan(4)
            ->live(onBlur: true)
            ->tooltip(fn (Get $get) => is_numeric($get('shipping_cost')) ? number_format($get('shipping_cost'), 2) : '')
            ->validationMessages(['numeric', 'min'])
            ->validationAttribute(__('resources/registeredOrder/strings.form.shipping_cost'));
    }

    protected static function getItemUnitField(): Select
    {
        return Select::make('unit')
            ->label(__('resources/registeredOrder/strings.form.unit'))
            ->options(__('resources/target/strings.metrics'))
            ->required()
            ->columnSpan(3)
            ->validationMessages([
                'required' => __('resources/registeredOrder/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.unit'));
    }

    protected static function getItemUnitPriceField(): TextInput
    {
        return TextInput::make('unit_price')
            ->label(__('resources/registeredOrder/strings.form.unit_price'))
            ->prefix('💰')
            ->required()
            ->numeric()
            ->columnSpan(3)
            ->minValue(0)
            ->live(onBlur: true)
            ->tooltip(fn (Get $get) => is_numeric($get('unit_price')) ? number_format($get('unit_price'), 2) : '')
            ->afterStateUpdated(fn(Get $get, Set $set) => static::updateItemLineTotal($get, $set))
            ->validationMessages([
                'required' => __('resources/registeredOrder/strings.form.validation_required'),
                'numeric' => __('resources/registeredOrder/strings.form.validation_numeric'),
                'min' => __('resources/registeredOrder/strings.form.validation_min_numeric_zero'),
            ])
            ->validationAttribute(__('resources/registeredOrder/strings.form.unit_price'));
    }

}
