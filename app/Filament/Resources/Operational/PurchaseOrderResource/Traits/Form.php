<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Traits;

use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\UpdatesFromProformaInvoice;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\UpdatesFromPurchaseRequests;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\UpdatesFromRegisteredOrders;
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
    use TotalCalculation, UpdatesFromProformaInvoice, UpdatesFromPurchaseRequests, UpdatesFromRegisteredOrders;

    public static function getBuyerField(): Select
    {
        return Select::make('buyer_id')
            ->label(__('resources/purchaseOrder/strings.form.buyer'))
            ->relationship(
                name: 'buyerCompany',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable(['name', 'english_name'])
            ->preload()
            ->required()
            ->different('seller_id')
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
                'different' => __('resources/purchaseOrder/strings.form.validation_seller_buyer_different'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.buyer'));
    }

    public static function getCurrencyField(): Select
    {
        return Select::make('currency_id')
            ->label(__('resources/purchaseOrder/strings.form.currency'))
            ->relationship(
                name: 'currency',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable()
            ->preload()
            ->required()
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.currency'));
    }

    public static function getExpectedDeliveryDateField(): DatePicker
    {
        return DatePicker::make('expected_delivery_date')
            ->label(__('resources/purchaseOrder/strings.form.expected_delivery_date'))
            ->native(false)
            ->adaptive()
            ->afterOrEqual('order_date')
            ->helperText(__('resources/purchaseOrder/strings.form.helper_expected_delivery_date'))
            ->validationMessages([
                'date' => __('resources/purchaseOrder/strings.form.validation_date'),
                'after_or_equal' => __('resources/purchaseOrder/strings.form.validation_after_or_equal_order_date'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.expected_delivery_date'));
    }

    public static function getIncotermsField(): Select
    {
        return Select::make('incoterms')
            ->label(__('resources/purchaseOrder/strings.form.incoterms'))
            ->options(__('resources/purchaseOrder/strings.general.delivery_terms'))
            ->validationAttribute(__('resources/purchaseOrder/strings.form.incoterms'));
    }

    public static function getItemDescriptionField(): Textarea
    {
        return Textarea::make('description')
            ->hiddenLabel()
            ->maxLength(65535)
            ->live()
            ->extraAttributes(fn (Get $get) => ['style' => ! $get('show_notes') ? 'display: none;' : ''])
            ->columnSpan('full')
            ->rules(['nullable', 'string', 'max:65535'])
            ->validationMessages([
                'max' => __('resources/purchaseOrder/strings.form.validation_max_string'),
                'string' => __('resources/purchaseOrder/strings.form.validation_string'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.item_description'));
    }

    public static function getItemGrossWeightField(): TextInput
    {
        return TextInput::make('gross_weight')
            ->label(__('resources/purchaseOrder/strings.form.gross_weight'))
            ->numeric()
            ->minValue(0)
            ->prefix('⏲️')
            ->columnSpan(2)
            ->validationMessages([
                'numeric' => __('resources/purchaseOrder/strings.form.validation_numeric'),
                'min' => __('resources/purchaseOrder/strings.form.validation_min_numeric_zero'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.gross_weight'))
            ->helperText(__('resources/purchaseOrder/strings.form.helper_gross_weight'));
    }

    public static function getItemNetWeightField(): TextInput
    {
        return TextInput::make('net_weight')
            ->label(__('resources/purchaseOrder/strings.form.net_weight'))
            ->numeric()
            ->minValue(0)
            ->prefix('⏲️')
            ->columnSpan(2)
            ->validationMessages([
                'numeric' => __('resources/purchaseOrder/strings.form.validation_numeric'),
                'min' => __('resources/purchaseOrder/strings.form.validation_min_numeric_zero'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.net_weight'));
    }

    public static function getItemNotesToggle(): Toggle
    {
        return Toggle::make('show_notes')
            ->label(__('resources/purchaseOrder/strings.form.add_notes'))
            ->onIcon('heroicon-s-check-circle')
            ->offIcon('heroicon-s-x-circle')
            ->onColor('success')
            ->offColor('danger')
            ->live()
            ->columnSpanFull()
            ->default(fn (Get $get) => filled($get('description')))
            ->afterStateHydrated(fn (Set $set, Get $get) => $set('show_notes', filled($get('description'))))
            ->afterStateUpdated(fn (?bool $state, Set $set) => ! $state ? $set('description', null) : null);
    }

    public static function getItemProductIdField(): Select
    {
        $titleAttribute = app()->getLocale() === 'fa' ? 'name' : 'english_name';

        return Select::make('product_id')
            ->label(__('resources/purchaseOrder/strings.form.product'))
            ->relationship(
                name: 'product',
                titleAttribute: $titleAttribute,
                modifyQueryUsing: fn ($query) => $query->whereNotNull($titleAttribute)
            )
            ->searchable(['name', 'english_name', 'code'])
            ->preload()
            ->required()
            ->columnSpan(4)
            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.product'));
    }

    public static function getItemQuantityField(): TextInput
    {
        return TextInput::make('quantity')
            ->label(__('resources/purchaseOrder/strings.form.quantity'))
            ->required()
            ->numeric()
            ->minValue(0.01)
            ->live(onBlur: true)
            ->columnSpan(2)
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
                'numeric' => __('resources/purchaseOrder/strings.form.validation_numeric'),
                'min' => __('resources/purchaseOrder/strings.form.validation_min_numeric'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.quantity'));
    }

    public static function getItemUnitField(): Select
    {
        return Select::make('unit')
            ->label(__('resources/purchaseOrder/strings.form.unit'))
            ->options(__('resources/general/strings.metrics'))
            ->required()
            ->columnSpan(3)
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.unit'));
    }

    public static function getItemUnitPriceField(): TextInput
    {
        return TextInput::make('unit_price')
            ->label(__('resources/purchaseOrder/strings.form.unit_price'))
            ->required()
            ->numeric()
            ->minValue(0)
            ->live(onBlur: true)
            ->prefix('💰')
            ->columnSpan(2)
            ->helperText(__('resources/purchaseOrder/strings.form.helper_unit_price'))
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
                'numeric' => __('resources/purchaseOrder/strings.form.validation_numeric'),
                'min' => __('resources/purchaseOrder/strings.form.validation_min_numeric_zero'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.unit_price'));
    }

    public static function getNotesField(): RichEditor
    {
        return RichEditor::make('notes')
            ->label(__('resources/purchaseOrder/strings.form.notes'))
            ->toolbarButtons([
                'bold',
                'underline',
                'link',
                'bulletList',
                'orderedList',
            ])
            ->columnSpanFull();
    }

    public static function getOrderDateField(): DatePicker
    {
        return DatePicker::make('order_date')
            ->label(__('resources/purchaseOrder/strings.form.order_date'))
            ->default(now())
            ->required()
            ->native(false)
            ->adaptive()
            ->validationMessages([
                'date' => __('resources/purchaseOrder/strings.form.validation_date'),
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.order_date'))
            ->helperText(__('resources/purchaseOrder/strings.form.helper_order_date'));
    }

    public static function getPackingDetailsField(): Textarea
    {
        return Textarea::make('packing_details')
            ->label(__('resources/purchaseOrder/strings.form.packing_details'))
            ->rows(3)
            ->maxLength(65535)
            ->validationMessages([
                'max' => __('resources/purchaseOrder/strings.form.validation_max_text'),
            ])
            ->columnSpanFull()
            ->validationAttribute(__('resources/purchaseOrder/strings.form.packing_details'));
    }

    public static function getPoNumberField(): TextInput
    {
        return TextInput::make('po_number')
            ->label(__('resources/purchaseOrder/strings.form.po_number'))
            ->required()
            ->readOnly()
            ->unique(ignoreRecord: true)
            ->maxLength(255)
            ->default(fn ($operation) => $operation == 'create' ? CodeGenerator::generate('po_number') : null)
            ->helperText(__('resources/purchaseOrder/strings.form.helper_po_number'))
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
                'unique' => __('resources/purchaseOrder/strings.form.validation_unique'),
                'max' => __('resources/purchaseOrder/strings.form.validation_max_string'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.po_number'));
    }

    public static function getProformaInvoicesField(): Select
    {
        return Select::make('proformaInvoices')
            ->label(__('resources/general/strings.relevant_module.form.proforma_invoices'))
            ->relationship(name: 'proformaInvoices')
            ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->formatted_name)
            ->columnSpanFull()
            ->multiple()
            ->preload()
            ->live()
            ->searchable()
            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                self::populateFromPI($state, $set);
                self::updateTotal($get, $set);
            })
            ->visible(fn (Get $get): bool => $get('source_type') === 'pi' || filled($get('proformaInvoices')))
            ->validationAttribute(__('resources/general/strings.relevant_module.form.proforma_invoices'));
    }

    public static function getPurchaseRequestsField(): Select
    {
        return Select::make('purchaseRequests')
            ->label(__('resources/general/strings.relevant_module.form.purchase_requests'))
            ->relationship(
                name: 'purchaseRequests',
                modifyQueryUsing: fn ($query) => $query->whereHas('status', fn ($statusQuery) => $statusQuery->whereIn('english_name', ['Authorized', 'Conditional']))->latest())
            ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->formatted_name)
            ->columnSpanFull()
            ->multiple()
            ->preload()
            ->live()
            ->searchable()
            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                static::populateFromPR($state, $set);
                self::updateTotal($get, $set);
            })
            ->visible(fn (Get $get): bool => $get('source_type') === 'pr' || filled($get('purchaseRequests')))
            ->validationAttribute(__('resources/general/strings.relevant_module.form.purchase_requests'));
    }

    public static function getRegisteredOrdersField(): Select
    {
        return Select::make('registeredOrders')
            ->label(__('resources/general/strings.relevant_module.form.registered_orders'))
            ->relationship(
                name: 'registeredOrders',
                modifyQueryUsing: fn ($query) => $query->whereHas('status', fn ($statusQuery) => $statusQuery->whereIn('english_name', ['Submitted']))->latest())
            ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->formatted_name)
            ->columnSpanFull()
            ->multiple()
            ->preload()
            ->live()
            ->searchable()
            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                self::populateFromRO($state, $set);
                self::updateTotal($get, $set);
            })
            ->visible(fn (Get $get): bool => $get('source_type') === 'ro' || filled($get('registeredOrders')))
            ->validationAttribute(__('resources/general/strings.relevant_module.form.registered_orders'));
    }

    public static function getSellerField(): Select
    {
        return Select::make('seller_id')
            ->label(__('resources/purchaseOrder/strings.form.seller'))
            ->relationship(
                name: 'sellerCompany',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable(['name', 'english_name'])
            ->preload()
            ->required()
            ->different('buyer_id')
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
                'different' => __('resources/purchaseOrder/strings.form.validation_seller_buyer_different'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.seller'));
    }

    public static function getShippingAddressField(): Textarea
    {
        return Textarea::make('shipping_address')
            ->label(__('resources/purchaseOrder/strings.form.shipping_address'))
            ->rows(4)
            ->columnSpanFull()
            ->maxLength(65535)
            ->validationMessages([
                'max' => __('resources/purchaseOrder/strings.form.validation_max_text'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.shipping_address'));
    }

    public static function getSourceTypeField(): Radio
    {
        return Radio::make('source_type')
            ->label(__('resources/general/strings.relevant_module.form.related_to'))
            ->inline()
            ->options([
                'pr' => __('resources/general/strings.relevant_module.form.purchase_requests_related'),
                'pi' => __('resources/general/strings.relevant_module.form.proforma_invoices_related'),
                'ro' => __('resources/general/strings.relevant_module.form.registered_orders_related'),
            ])
            ->columnSpanFull()
            ->default(null)
            ->live()
            ->afterStateHydrated(function (Set $set, Get $get, ?Model $record) {
                if (filled($get('source_type'))) {
                    return;
                }

                $set('source_type', match (true) {
                    $record?->purchaseRequests()->exists() => 'pr',
                    $record?->proformaInvoices()->exists() => 'pi',
                    $record?->registeredOrders()->exists() => 'ro',
                    default => null,
                });
            });
    }

    public static function getStatusField(): Select
    {
        return Select::make('status_id')
            ->label(__('resources/purchaseOrder/strings.form.status'))
            ->relationship(
                name: 'status',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->default(fn ($operation): ?int => $operation === 'create' ? Status::findBy('Purchase Order Status', 'Submitted')?->id : null)
            ->searchable()
            ->preload()
            ->required()
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.status'))
            ->helperText(__('resources/purchaseOrder/strings.form.helper_status'));
    }

    public static function getTotalAmountField(): TextEntry
    {
        return TextEntry::make('total_amount')
            ->label(__('resources/purchaseOrder/strings.form.total_amount'))
            ->columnSpan(2)
            ->formatStateUsing(fn (Get $get) => is_numeric($get('total_amount')) ? '💰 '.number_format($get('total_amount'), 2) : $get('total_amount'));
    }

    public static function getTotalQuantityField(): TextEntry
    {
        return TextEntry::make('total_quantity')
            ->label(__('resources/purchaseOrder/strings.form.total_quantity'))
            ->columnSpan(2)
            ->formatStateUsing(fn (Get $get) => is_numeric($get('total_quantity')) ? '📦 '.number_format($get('total_quantity'), 2) : $get('total_quantity'));
    }

    public static function getValidityDateField(): DatePicker
    {
        return DatePicker::make('validity_date')
            ->label(__('resources/purchaseOrder/strings.form.validity_date'))
            ->required()
            ->native(false)
            ->adaptive()
            ->afterOrEqual('order_date')
            ->helperText(__('resources/purchaseOrder/strings.form.helper_validity_date'))
            ->validationMessages([
                'date' => __('resources/purchaseOrder/strings.form.validation_date'),
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
                'after_or_equal' => __('resources/purchaseOrder/strings.form.validation_after_or_equal_order_date'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.validity_date'));
    }
}
