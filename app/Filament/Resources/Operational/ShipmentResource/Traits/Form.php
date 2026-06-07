<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Traits;

use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Status;
use App\Services\CodeGenerator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;

trait Form
{
    public static function getBlNumberField(): TextInput
    {
        return TextInput::make('bl_number')
            ->label(__('resources/shipment/strings.form.bl_number'))
            ->maxLength(255)
            ->validationMessages([
                'max' => __('resources/shipment/strings.form.validation.max'),
            ])
            ->validationAttribute(__('resources/shipment/strings.form.bl_number'));
    }

    public static function getBookingNoField(): TextInput
    {
        return TextInput::make('booking_no')
            ->label(__('resources/shipment/strings.form.booking_no'))
            ->maxLength(255)
            ->validationMessages([
                'max' => __('resources/shipment/strings.form.validation.max'),
            ])
            ->validationAttribute(__('resources/shipment/strings.form.booking_no'));
    }

    public static function getCompanyField(): Select
    {
        return Select::make('company_id')
            ->label(__('resources/shipment/strings.form.carrier'))
            ->relationship(
                name: 'carrier',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable(['name', 'english_name'])
            ->preload()
            ->required()
            ->validationMessages([
                'required' => __('resources/shipment/strings.form.validation.required'),
            ])
            ->validationAttribute(__('resources/shipment/strings.form.carrier'));
    }

    public static function getContainerNoField(): Select
    {
        return Select::make('container_no')
            ->label(__('resources/shipment/strings.form.container_no'))
            ->options(array_combine(range(1, 30), range(1, 30)))
            ->searchable();
    }

    public static function getContainerStatusField(): Select
    {
        return Select::make('container_status_id')
            ->label(__('resources/shipment/strings.form.container_status'))
            ->relationship(
                name: 'containerStatus',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
                modifyQueryUsing: fn($query) => $query->where('english_type', Shipment::TYPE_CONTAINER_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getContainerTypeField(): Select
    {
        return Select::make('container_type')
            ->label(__('resources/shipment/strings.form.container_type'))
            ->options(__('resources/shipment/strings.form.container_types_with_opt'))
            ->searchable();
    }

    public static function getContractNoField(): TextInput
    {
        return TextInput::make('contract_no')
            ->label(__('resources/shipment/strings.form.contract_no'))
            ->maxLength(255)
            ->validationMessages([
                'max' => __('resources/shipment/strings.form.validation.max'),
            ])
            ->validationAttribute(__('resources/shipment/strings.form.contract_no'));
    }

    public static function getCustomsQuantityField(): TextInput
    {
        return TextInput::make('customs_quantity')
            ->label(__('resources/shipment/strings.form.customs_quantity'))
            ->numeric()
            ->minValue(0)
            ->step(0.01)
            ->validationMessages([
                'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                'min' => __('resources/shipment/strings.form.validation.min_numeric_zero'),
            ])
            ->validationAttribute(__('resources/shipment/strings.form.customs_quantity'))
            ->hint(fn($get) => delimiter($get('customs_quantity')));
    }

    public static function getDocStatusField(): Select
    {
        return Select::make('doc_status_id')
            ->label(__('resources/shipment/strings.form.doc_status'))
            ->relationship(
                name: 'docStatus',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
                modifyQueryUsing: fn($query) => $query->where('english_type', Shipment::TYPE_DOC_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getDocsField(): Repeater
    {
        $defaults = __('resources/shipment/strings.form.docs_options');
        $options = is_array($defaults) ? collect($defaults)->except('track')->all() : [];
        $locked = fn(Get $get) => (bool)$get('../../doc_tracking');

        return Repeater::make('docs')
            ->hiddenLabel()
            ->schema([
                TextInput::make('name')
                    ->label(__('resources/shipment/strings.form.doc_name'))
                    ->required()
                    ->columnSpan(3)
                    ->readOnly($locked)
                    ->dehydrated()
                    ->afterStateHydrated(fn(TextInput $component, $state) => is_string($state) && array_key_exists($state, $options)
                        ? $component->state($options[$state])
                        : null)
                    ->formatStateUsing(fn($state) => is_string($state) && isset($options[$state]) ? $options[$state] : $state)
                    ->dehydrateStateUsing(fn($state) => ($key = array_search($state, $options, true)) !== false ? $key : $state)
                    ->rules([
                        function () use ($options) {
                            return function (string $attribute, $value, \Closure $fail) use ($options) {
                                if (in_array($value, $options, true) || in_array($value, array_keys($options), true)) return;
                                if (!preg_match('/^[a-zA-Z0-9\s\(\)\-_]+$/u', $value)) $fail(__('resources/shipment/strings.form.validation.english_only'));
                            };
                        },
                    ]),
                Toggle::make('received')
                    ->label('⇄')
                    ->inline(false)
                    ->onIcon('heroicon-s-check')
                    ->offIcon('heroicon-s-x-mark')
                    ->onColor('success')
                    ->offColor('danger')
                    ->disabled($locked)
                    ->dehydrated()
                    ->columnSpan(1),
            ])
            ->default(fn() => collect($options)->map(fn($label, $key) => ['name' => $label, 'received' => false])->values()->all())
            ->columns(4)
            ->columnSpanFull()
            ->grid(3)
            ->addable(fn(Get $get) => !(bool)$get('doc_tracking'))
            ->deletable(fn(Get $get) => !(bool)$get('doc_tracking'))
            ->addActionLabel(__('resources/shipment/strings.form.add_doc'))
            ->collapsible()
            ->itemLabel(fn() => null);
    }

    public static function getEtaField()
    {
        return DatePicker::make('eta')
            ->label(__('resources/shipment/strings.form.eta'))
            ->native(false)
            ->adaptive();
    }

    public static function getEtdField()
    {
        return DatePicker::make('etd')
            ->label(__('resources/shipment/strings.form.etd'))
            ->native(false)
            ->adaptive();
    }

    public static function getExitDateField()
    {
        return DatePicker::make('exit_date')
            ->label(__('resources/shipment/strings.form.exit_date'))
            ->native(false)
            ->adaptive();
    }

    public static function getNotesField(): Textarea
    {
        return Textarea::make('notes')
            ->label(__('resources/shipment/strings.form.notes'))
            ->rows(3)
            ->columnSpanFull();
    }

    public static function getOperationStatusField(): Select
    {
        return Select::make('operation_status_id')
            ->label(__('resources/shipment/strings.form.operation_status'))
            ->relationship(
                name: 'operationStatus',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
                modifyQueryUsing: fn($query) => $query->where('english_type', Shipment::TYPE_OPERATION_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getPartField(): Select
    {
        return Select::make('part')
            ->label(__('resources/shipment/strings.form.part'))
            ->options(function (Get $get) {
                [$registeredOrderId, $contractNo] = [$get('registered_order_id'), $get('contract_no')];

                if (!$registeredOrderId || !$contractNo) {
                    return array_combine(range(1, 50), range(1, 50));
                }

                $available = Shipment::getPartData($registeredOrderId, $contractNo, $get('id'))['available'];
                return array_combine($available, $available);
            })
            ->default(function (Get $get) {
                [$registeredOrderId, $contractNo] = [$get('registered_order_id'), $get('contract_no')];

                return (!$registeredOrderId || !$contractNo)
                    ? 1
                    : Shipment::getPartData($registeredOrderId, $contractNo, $get('id'))['next'];
            })
            ->live()
            ->required()
            ->searchable()
            ->helperText(__('resources/shipment/strings.form.helper_part'))
            ->validationMessages([
                'required' => __('resources/shipment/strings.form.validation.required'),
            ])
            ->validationAttribute(__('resources/shipment/strings.form.part'));
    }

    public static function getRegisteredOrderField(): Select
    {
        return Select::make('registered_order_id')
            ->label(__('resources/shipment/strings.form.registered_order'))
            ->relationship('registeredOrder', 'ro_number')
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->formatted_name_without_date)
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->afterStateUpdated(fn($state, Set $set) => $set('contract_no', RegisteredOrder::find($state, ['contract_no'])?->contract_no ?? null))
            ->validationMessages([
                'required' => __('resources/shipment/strings.form.validation.required'),
            ])
            ->validationAttribute(__('resources/shipment/strings.form.registered_order'));
    }

    public static function getRemittanceAmountField(): TextInput
    {
        return TextInput::make('remittance_amount')
            ->label(__('resources/shipment/strings.form.remittance_amount'))
            ->numeric()
            ->minValue(0)
            ->prefix('💰')
            ->validationMessages([
                'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                'min' => __('resources/shipment/strings.form.validation.min_numeric_zero'),
            ])
            ->validationAttribute(__('resources/shipment/strings.form.remittance_amount'))
            ->hint(fn($get) => delimiter($get('remittance_amount')));
    }

    public static function getShipmentNoField(): TextInput
    {
        return TextInput::make('shipment_no')
            ->label(__('resources/shipment/strings.form.shipment_no'))
            ->required()
            ->unique(ignoreRecord: true)
            ->default(fn($operation) => $operation == 'create' ? CodeGenerator::generate('shipment_no') : null)
            ->validationMessages([
                'required' => __('resources/shipment/strings.form.validation.required'),
                'unique' => __('resources/shipment/strings.form.validation.unique'),
            ])
            ->validationAttribute(__('resources/shipment/strings.form.shipment_no'));
    }

    public static function getShipmentStatusField(): Select
    {
        return Select::make('shipment_status_id')
            ->label(__('resources/shipment/strings.form.shipment_status'))
            ->relationship(
                name: 'trackingStatus',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
                modifyQueryUsing: fn($query) => $query->where('english_type', Shipment::TYPE_TRACKING_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getShippedQuantityField(): TextInput
    {
        return TextInput::make('shipped_quantity')
            ->label(__('resources/shipment/strings.form.shipped_quantity'))
            ->numeric()
            ->minValue(0)
            ->step(0.01)
            ->validationMessages([
                'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                'min' => __('resources/shipment/strings.form.validation.min_numeric_zero'),
            ])
            ->validationAttribute(__('resources/shipment/strings.form.shipped_quantity'))
            ->hint(fn($get) => delimiter($get('shipped_quantity')));
    }

    public static function getSmartTracerField(): Toggle
    {
        return Toggle::make('doc_tracking')
            ->label(__('resources/shipment/strings.form.docs_options.track'))
            ->default(true)
            ->live()
            ->inline(false)
            ->onIcon('heroicon-s-bolt')
            ->offIcon('heroicon-s-bolt-slash')
            ->onColor('success')
            ->offColor('gray')
            ->columnSpanFull();
    }

    public static function getStatusField(): Select
    {
        return Select::make('status_id')
            ->label(__('resources/shipment/strings.form.status'))
            ->relationship(
                name: 'status',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
                modifyQueryUsing: fn($query) => $query->where('english_type', Shipment::TYPE_SHIPMENT_STATUS)
            )
            ->default(fn($operation): ?int => $operation === 'create' ? Status::findBy(Shipment::TYPE_SHIPMENT_STATUS, 'Processing')?->id : null)
            ->searchable()
            ->preload()
            ->required()
            ->validationMessages([
                'required' => __('resources/shipment/strings.form.validation.required'),
            ])
            ->validationAttribute(__('resources/shipment/strings.form.status'));
    }

    public static function getWarehouseDateField()
    {
        return DatePicker::make('warehouse_date')
            ->label(__('resources/shipment/strings.form.warehouse_date'))
            ->native(false)
            ->adaptive();
    }
}
