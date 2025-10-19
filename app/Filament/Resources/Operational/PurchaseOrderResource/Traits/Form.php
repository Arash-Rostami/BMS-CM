<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Traits;

use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\UpdatesFromPurchaseRequests;
use App\Models\Status;
use App\Services\CodeGenerator;
use App\Services\FileUploadManager;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;


trait Form
{
    use TotalCalculation, UpdatesFromPurchaseRequests;

    public static function getAttachmentsField(): FileUpload
    {
        return FileUpload::make('attachments')
            ->label(__('resources/purchaseOrder/strings.form.attachments'))
            ->multiple()
            ->disk('public')
            ->visibility('public')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'application/pdf', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',])
            ->maxSize(2500)
            ->previewable()
            ->openable()
            ->columnSpanFull()
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

    public static function getBuyerField(): Select
    {
        return Select::make('buyer_id')
            ->label(__('resources/purchaseOrder/strings.form.buyer'))
            ->relationship(
                name: 'buyer',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable(['name', 'english_name'])
            ->preload()
            ->required()
            ->different('supplier_id')
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
                'different' => __('resources/purchaseOrder/strings.form.validation_supplier_buyer_different'),
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
            ->afterOrEqual('order_date')
            ->validationMessages([
                'after_or_equal' => __('resources/purchaseOrder/strings.form.validation_after_or_equal_order_date'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.expected_delivery_date'));
    }

    public static function getIncotermsField(): Select
    {
        return Select::make('incoterms')
            ->label(__('resources/purchaseOrder/strings.form.incoterms'))
            ->options(__('resources/proformaInvoice/strings.general.delivery_terms'))
            ->validationAttribute(__('resources/purchaseOrder/strings.form.incoterms'));
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
            ->validationAttribute(__('resources/purchaseOrder/strings.form.gross_weight'));
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

    public static function getItemProductIdField(): Select
    {
        return Select::make('product_id')
            ->label(__('resources/purchaseOrder/strings.form.product'))
            ->relationship(
                name: 'product',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable(['name', 'english_name', 'code'])
            ->preload()
            ->required()
            ->columnSpan(4)
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
            ->options(__('resources/target/strings.metrics'))
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
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.order_date'));
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
            ->unique(ignoreRecord: true)
            ->maxLength(255)
            ->default(fn($operation) => $operation == 'create' ? CodeGenerator::generate() : null)
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
                'unique' => __('resources/purchaseOrder/strings.form.validation_unique'),
                'max' => __('resources/purchaseOrder/strings.form.validation_max_string'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.po_number'));
    }

    public static function getPurchaseRequestsField(): Select
    {
        return Select::make('purchaseRequests')
            ->label(__('resources/purchaseOrder/strings.form.purchase_requests'))
            ->relationship(
                name: 'purchaseRequests',
                modifyQueryUsing: fn($query) => $query->whereHas('status', fn($statusQuery) => $statusQuery->whereIn('english_name', ['Authorized', 'Conditional']))->latest())
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->formatted_name)
            ->columnSpanFull()
            ->multiple()
            ->preload()
            ->live()
            ->searchable()
            ->afterStateHydrated(function ($state, Get $get, Set $set) {
                static::populateFromPR($state, $set);
                self::updateTotal($get, $set);
            })
            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                static::populateFromPR($state, $set);
                self::updateTotal($get, $set);
            })
            ->validationAttribute(__('resources/purchaseOrder/strings.form.purchase_requests'));
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

    public static function getStatusField(): Select
    {
        return Select::make('status_id')
            ->label(__('resources/purchaseOrder/strings.form.status'))
            ->relationship(
                name: 'status',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->default(fn($operation): ?int => $operation === 'create' ? Status::findBy('Purchase Order Status', 'Submitted')?->id : null)
            ->searchable()
            ->preload()
            ->required()
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.status'));
    }

    public static function getSupplierField(): Select
    {
        return Select::make('supplier_id')
            ->label(__('resources/purchaseOrder/strings.form.supplier'))
            ->relationship(
                name: 'supplier',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable(['name', 'english_name'])
            ->preload()
            ->required()
            ->different('buyer_id')
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
                'different' => __('resources/purchaseOrder/strings.form.validation_supplier_buyer_different'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.supplier'));
    }

    public static function getTotalAmountField(): TextEntry
    {
        return TextEntry::make('total_amount')
            ->label(__('resources/purchaseOrder/strings.form.total_amount'))
            ->columnSpan(2)
            ->formatStateUsing(fn(Get $get) => is_numeric($get('total_amount')) ? '💰 ' . number_format($get('total_amount'), 2) : $get('total_amount'));
    }

    public static function getTotalQuantityField(): TextEntry
    {
        return TextEntry::make('total_quantity')
            ->label(__('resources/purchaseOrder/strings.form.total_quantity'))
            ->columnSpan(2)
            ->formatStateUsing(fn(Get $get) => is_numeric($get('total_quantity')) ? '📦 ' . number_format($get('total_quantity'), 2) : $get('total_quantity'));
    }

    public static function getValidityDateField(): DatePicker
    {
        return DatePicker::make('validity_date')
            ->label(__('resources/purchaseOrder/strings.form.validity_date'))
            ->required()
            ->native(false)
            ->afterOrEqual('order_date')
            ->validationMessages([
                'required' => __('resources/purchaseOrder/strings.form.validation_required'),
                'after_or_equal' => __('resources/purchaseOrder/strings.form.validation_after_or_equal_order_date'),
            ])
            ->validationAttribute(__('resources/purchaseOrder/strings.form.validity_date'));
    }
}
