<?php

namespace App\Filament\Resources\Operational\PaymentResource\Traits;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use App\Services\CodeGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;

trait Form
{
    use Calculation;

    public static function getAccountNoField(): TextInput
    {
        return TextInput::make('account_no')
            ->label(__('resources/payment/strings.form.account_no'))
            ->maxLength(255)
            ->validationMessages([
                'max' => __('resources/payment/strings.form.validation_max_length'),
            ])
            ->validationAttribute(__('resources/payment/strings.form.account_no'));
    }

    public static function getBankAddressField(): Textarea
    {
        return Textarea::make('bank_address')
            ->label(__('resources/payment/strings.form.bank_address'))
            ->rows(3)
            ->columnSpanFull()
            ->validationAttribute(__('resources/payment/strings.form.bank_address'));
    }

    public static function getBankChargesField(): TextInput
    {
        return TextInput::make('bank_charges')
            ->label(__('resources/payment/strings.form.bank_charges'))
            ->numeric()
            ->minValue(0)
            ->default(0)
            ->live(onBlur: true)
            ->afterStateUpdated(fn (Get $get, Set $set) => static::updateComputations($get, $set))
            ->validationMessages([
                'numeric' => __('resources/payment/strings.form.validation_numeric'),
                'min' => __('resources/payment/strings.form.validation_min_numeric_zero'),
            ])
            ->hintAction(
                Action::make('help')
                    ->icon('heroicon-o-question-mark-circle')
                    ->hiddenLabel()
                    ->tooltip(__('resources/payment/strings.form.tooltips.bank_charges'))
            )
            ->hint(fn (Get $get) => is_numeric($get('bank_charges')) ? preciseNumber($get('bank_charges')) : $get('bank_charges'))
            ->validationAttribute(__('resources/payment/strings.form.bank_charges'));
    }

    public static function getBankIdField(): Select
    {
        return Select::make('bank_id')
            ->label(__('resources/payment/strings.form.bank'))
            ->relationship(
                name: 'bank',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable(['name', 'english_name'])
            ->preload()
            ->validationAttribute(__('resources/payment/strings.form.bank'));
    }

    public static function getBeneficiaryAddressField(): Textarea
    {
        return Textarea::make('beneficiary_address')
            ->label(__('resources/payment/strings.form.beneficiary_address'))
            ->rows(3)
            ->columnSpanFull()
            ->validationAttribute(__('resources/payment/strings.form.beneficiary_address'));
    }

    public static function getBeneficiaryNameField(): TextInput
    {
        return TextInput::make('beneficiary_name')
            ->label(__('resources/payment/strings.form.beneficiary_name'))
            ->maxLength(255)
            ->validationMessages([
                'max' => __('resources/payment/strings.form.validation_max_length'),
            ])
            ->validationAttribute(__('resources/payment/strings.form.beneficiary_name'));
    }

    public static function getCurrencyField(): Select
    {
        return Select::make('currency_id')
            ->label(__('resources/payment/strings.form.currency'))
            ->relationship(
                name: 'currency',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable()
            ->preload()
            ->disabled(fn (Get $get): bool => empty($get('targetable_type')) || empty($get('targetable_id')))
            ->required()
            ->validationMessages([
                'required' => __('resources/payment/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/payment/strings.form.currency'));
    }

    public static function getExchangeRateField(): TextInput
    {
        return TextInput::make('exchange_rate')
            ->label(__('resources/payment/strings.form.exchange_rate'))
            ->numeric()
            ->minValue(0)
            ->live(onBlur: true)
            ->afterStateUpdated(fn (Get $get, Set $set) => static::updateComputations($get, $set))
            ->validationMessages([
                'numeric' => __('resources/payment/strings.form.validation_numeric'),
                'min' => __('resources/payment/strings.form.validation_min_numeric_zero'),
            ])
            ->hintAction(
                Action::make('help')
                    ->icon('heroicon-o-question-mark-circle')
                    ->hiddenLabel()
                    ->tooltip(__('resources/payment/strings.form.tooltips.exchange_rate'))
            )
            ->hint(fn (Get $get) => is_numeric($get('exchange_rate')) ? preciseNumber($get('exchange_rate')) : $get('exchange_rate'))
            ->validationAttribute(__('resources/payment/strings.form.exchange_rate'));
    }

    public static function getIbanField(): TextInput
    {
        return TextInput::make('iban')
            ->label(__('resources/payment/strings.form.iban'))
            ->maxLength(255)
            ->validationMessages([
                'max' => __('resources/payment/strings.form.validation_max_length'),
            ])
            ->helperText(__('resources/payment/strings.form.helper_iban'))
            ->validationAttribute(__('resources/payment/strings.form.iban'));
    }

    public static function getNotesField(): Textarea
    {
        return Textarea::make('notes')
            ->label(__('resources/payment/strings.form.notes'))
            ->rows(3)
            ->columnSpanFull()
            ->validationAttribute(__('resources/payment/strings.form.notes'));
    }

    public static function getPayableAmountField(): TextInput
    {
        return TextInput::make('payable_amount')
            ->label(__('resources/payment/strings.form.payable_amount'))
            ->numeric()
            ->hint(fn (Get $get) => is_numeric($get('payable_amount')) ? preciseNumber($get('payable_amount')) : $get('payable_amount'))
            ->hintAction(
                Action::make('help')
                    ->icon('heroicon-o-question-mark-circle')
                    ->hiddenLabel()
                    ->tooltip(__('resources/payment/strings.form.tooltips.payable_amount'))
            )
            ->minValue(0)
            ->live(onBlur: true)
            ->required()
            ->afterStateUpdated(fn (Get $get, Set $set) => static::updateComputations($get, $set))
            ->validationMessages([
                'numeric' => __('resources/payment/strings.form.validation_numeric'),
                'min' => __('resources/payment/strings.form.validation_min_numeric_zero'),
                'required' => __('resources/payment/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/payment/strings.form.payable_amount'));
    }

    public static function getPayeeField(): Select
    {
        return Select::make('payee_id')
            ->label(__('resources/payment/strings.form.payee'))
            ->relationship(
                name: 'payee',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable(['name', 'english_name'])
            ->preload()
            ->required()
            ->validationMessages([
                'required' => __('resources/payment/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/payment/strings.form.payee'));
    }

    public static function getPaymentDateField()
    {
        return DatePicker::make('payment_date')
            ->label(__('resources/payment/strings.form.payment_date'))
            ->default(now())
            ->disabled(fn (Get $get): bool => empty($get('targetable_type')) || empty($get('targetable_id')))
            ->hidden(fn (Get $get): bool => empty($get('targetable_type')) || empty($get('targetable_id')))
            ->native(false)
            ->adaptive()
            ->required()
            ->validationMessages([
                'date' => __('resources/payment/strings.form.validation_date'),
                'required' => __('resources/payment/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/payment/strings.form.payment_date'));
    }

    public static function getPaymentDeadlineField()
    {
        return DatePicker::make('payment_deadline')
            ->label(__('resources/payment/strings.form.payment_deadline'))
            ->native(false)
            ->afterOrEqual('payment_date')
            ->adaptive()
            ->validationMessages([
                'date' => __('resources/payment/strings.form.validation_date'),
                'after_or_equal' => __('resources/payment/strings.form.validation_date_after_or_equal', ['date' => __('resources/payment/strings.form.payment_date')]),
            ])
            ->validationAttribute(__('resources/payment/strings.form.payment_deadline'));
    }

    public static function getPaymentNoField(): TextInput
    {
        return TextInput::make('payment_no')
            ->label(__('resources/payment/strings.form.payment_no'))
            ->required()
            ->readOnly()
            ->maxLength(255)
            ->unique(ignoreRecord: true)
            ->default(fn ($operation) => $operation == 'create' ? CodeGenerator::generate('payment_no') : null)
            ->validationMessages([
                'required' => __('resources/payment/strings.form.validation_required'),
                'unique' => __('resources/payment/strings.form.validation_unique'),
                'max' => __('resources/payment/strings.form.validation_max_length'),
            ])
            ->validationAttribute(__('resources/payment/strings.form.payment_no'));
    }

    public static function getPayorField(): Select
    {
        return Select::make('payor_id')
            ->label(__('resources/payment/strings.form.payor'))
            ->relationship(
                name: 'payor',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable(['name', 'english_name'])
            ->preload()
            ->required()
            ->validationMessages([
                'required' => __('resources/payment/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/payment/strings.form.payor'));
    }

    public static function getStatusField(): Select
    {
        return Select::make('status_id')
            ->label(__('resources/payment/strings.form.status'))
            ->relationship(
                name: 'status',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
                modifyQueryUsing: fn ($query) => $query->where('english_type', Payment::TYPE_PAYMENT)
            )
            ->disabled(fn (Get $get): bool => empty($get('targetable_type')) || empty($get('targetable_id')))
            ->hidden(fn (Get $get): bool => empty($get('targetable_type')) || empty($get('targetable_id')))
            ->searchable()
            ->preload()
            ->required()
            ->validationMessages([
                'required' => __('resources/payment/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/payment/strings.form.status'))
            ->helperText(__('resources/payment/strings.form.helper_status'));
    }

    public static function getSummaryFields(): array
    {
        return [
            TextEntry::make('calculated_total_display')
                ->label(__('resources/payment/strings.form.summary_calculated_total'))
                ->state(fn (Get $get) => '💰 '.preciseNumber(static::computeCalculatedTotal($get))),

            TextEntry::make('total_display')
                ->label(__('resources/payment/strings.form.total_amount_entered'))
                ->state(fn (Get $get) => is_numeric($get('total_amount')) ? '📝 '.preciseNumber((float) $get('total_amount')) : '-'),

            TextEntry::make('total_ratio_display')
                ->label(__('resources/payment/strings.form.summary_total_ratio'))
                ->state(function (Get $get) {
                    $ratio = static::computeTotalRatio(static::computeCalculatedTotal($get), static::computeTotal($get));

                    return $ratio !== null ? preciseNumber($ratio * 100).'%' : '-';
                }),
        ];
    }

    public static function getSwiftField(): TextInput
    {
        return TextInput::make('swift')
            ->label(__('resources/payment/strings.form.swift'))
            ->maxLength(255)
            ->validationMessages([
                'max' => __('resources/payment/strings.form.validation_max_length'),
            ])
            ->validationAttribute(__('resources/payment/strings.form.swift'));
    }

    public static function getTargetableField(): MorphToSelect
    {
        return MorphToSelect::make('targetable')
            ->label(__('resources/payment/strings.form.targetable'))
            ->types([
                Type::make(PurchaseOrder::class)
                    ->label(__('resources/payment/strings.form.targetable_purchase_order'))
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->formatted_name)
                    ->searchColumns(['po_number']),
                Type::make(RegisteredOrder::class)
                    ->label(__('resources/payment/strings.form.targetable_registered_order'))
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->formatted_name)
                    ->searchColumns(['ro_number', 'contract_no']),
            ])
            ->searchable()
            ->required()
            ->live()
            ->live(onBlur: true)
            ->columnSpanFull()
            ->validationMessages([
                'required' => __('resources/payment/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/payment/strings.form.targetable'));
    }

    public static function getTotalAmountField(): TextInput
    {
        return TextInput::make('total_amount')
            ->label(__('resources/payment/strings.form.total_amount'))
            ->numeric()
            ->minValue(0)
            ->live(onBlur: true)
            ->afterStateUpdated(fn (Get $get, Set $set) => static::updateComputations($get, $set))
            ->validationMessages([
                'numeric' => __('resources/payment/strings.form.validation_numeric'),
                'min' => __('resources/payment/strings.form.validation_min_numeric_zero'),
            ])
            ->hintAction(
                Action::make('help')
                    ->icon('heroicon-o-question-mark-circle')
                    ->hiddenLabel()
                    ->tooltip(__('resources/payment/strings.form.tooltips.total_amount'))
            )
            ->hint(fn (Get $get) => is_numeric($get('total_amount')) ? preciseNumber($get('total_amount')) : $get('total_amount'))
            ->validationAttribute(__('resources/payment/strings.form.total_amount'));
    }
}
