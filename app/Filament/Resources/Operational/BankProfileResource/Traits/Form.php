<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Traits;

use App\Models\BankProfile;
use App\Models\Category;
use App\Models\Product;
use App\Models\RegisteredOrder;
use App\Models\Status;
use App\Services\CodeGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;

trait Form
{

    use Calculation;

    public static function getAllocationDateField()
    {
        return DatePicker::make('allocation_date')
            ->label(__('resources/bankProfile/strings.form.allocation_date'))
            ->native(false)
            ->adaptive()
            ->live(onBlur: true)
            ->validationAttribute(__('resources/bankProfile/strings.form.allocation_date'));
    }

    public static function getBankField(): Select
    {
        return Select::make('bank_id')
            ->label(__('resources/bankProfile/strings.form.bank'))
            ->relationship(
                name: 'bank',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable(['name', 'english_name'])
            ->preload()
            ->validationAttribute(__('resources/bankProfile/strings.form.bank'));
    }

    public static function getBpNumberField(): TextInput
    {
        return TextInput::make('bp_number')
            ->label(__('resources/bankProfile/strings.form.bp_number'))
            ->required()
            ->readOnly()
            ->maxLength(255)
            ->unique(ignoreRecord: true)
            ->default(fn($operation) => $operation == 'create' ? CodeGenerator::generate('bp_number') : null)
            ->validationMessages([
                'required' => __('resources/bankProfile/strings.form.validation_required'),
                'unique' => __('resources/bankProfile/strings.form.validation_unique'),
                'max' => __('resources/bankProfile/strings.form.validation_max_length', ['max' => 255]),
            ])
            ->validationAttribute(__('resources/bankProfile/strings.form.bp_number'));
    }

    public static function getCommissionAmountPurchasedField(): TextInput
    {
        return TextInput::make('commission_amount_purchased')
            ->label(__('resources/bankProfile/strings.form.summary_commission_amount'))
            ->numeric()
            ->minValue(0)
            ->live(onBlur: true)
            ->readOnly(fn(Get $get) => ($get('commission_input_mode') ?? 'rate') !== 'amount')
            ->columnSpan(1)
            ->afterStateUpdated(fn(Get $get, Set $set) => static::updateComputations($get, $set))
            ->validationMessages([
                'numeric' => __('resources/bankProfile/strings.form.validation_numeric'),
                'min' => __('resources/bankProfile/strings.form.validation_min_numeric_zero'),
            ])
            ->hintAction(
                Action::make('help')
                    ->icon('heroicon-o-question-mark-circle')
                    ->label('')
                    ->tooltip(__('resources/bankProfile/strings.form.tooltips.commission_amount_purchased'))
            )
            ->hint(fn(Get $get) => is_numeric($get('commission_amount_purchased')) ? delimiter($get('commission_amount_purchased')) : $get('commission_amount_purchased'))
            ->helperText(__('resources/bankProfile/strings.form.helper_commission_amount_purchased'))
            ->validationAttribute(__('resources/bankProfile/strings.form.summary_commission_amount'));
    }

    public static function getCommissionInputModeField(): ToggleButtons
    {
        return ToggleButtons::make('commission_input_mode')
            ->label(__('resources/bankProfile/strings.form.commission_input_mode'))
            ->options([
                'rate' => __('resources/bankProfile/strings.form.commission_mode_rate'),
                'amount' => __('resources/bankProfile/strings.form.commission_mode_amount'),
            ])
            ->icons([
                'rate' => 'heroicon-m-percent-badge',
                'amount' => 'heroicon-m-banknotes',
            ])
            ->grouped()
            ->default('rate')
            ->live()
            ->afterStateUpdated(fn(Get $get, Set $set) => static::updateComputations($get, $set))
            ->helperText(__('resources/bankProfile/strings.form.helper_commission_input_mode'))
            ->columnSpanFull();
    }

    public static function getCommissionRateField(): TextInput
    {
        return TextInput::make('commission_rate')
            ->label(__('resources/bankProfile/strings.form.commission_rate'))
            ->numeric()
            ->minValue(0)
            ->live(onBlur: true)
            ->readOnly(fn(Get $get) => ($get('commission_input_mode') ?? 'rate') !== 'rate')
            ->dehydrated(true)
            ->columnSpan(1)
            ->afterStateUpdated(fn(Get $get, Set $set) => static::updateComputations($get, $set))
            ->validationMessages([
                'numeric' => __('resources/bankProfile/strings.form.validation_numeric'),
                'min' => __('resources/bankProfile/strings.form.validation_min_numeric_zero'),
            ])
            ->hintAction(
                Action::make('help')
                    ->icon('heroicon-o-question-mark-circle')
                    ->label('')
                    ->tooltip(__('resources/bankProfile/strings.form.tooltips.commission_rate'))
            )
            ->hint(fn(Get $get) => is_numeric($get('commission_rate')) ? delimiter($get('commission_rate')) : $get('commission_rate'))
            ->helperText(__('resources/bankProfile/strings.form.helper_commission_rate'))
            ->validationAttribute(__('resources/bankProfile/strings.form.commission_rate'));
    }

    public static function getCompanyField(): Select
    {
        return Select::make('company_id')
            ->label(__('resources/bankProfile/strings.form.company'))
            ->relationship(
                name: 'company',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable(['name', 'english_name'])
            ->preload()
            ->validationAttribute(__('resources/bankProfile/strings.form.company'));
    }

    public static function getConversionRateField(): TextInput
    {
        return TextInput::make('conversion_rate')
            ->label(__('resources/bankProfile/strings.form.conversion_rate'))
            ->numeric()
            ->readOnly()
            ->validationMessages([
                'numeric' => __('resources/bankProfile/strings.form.validation_numeric'),
            ])
            ->hintAction(
                Action::make('help')
                    ->icon('heroicon-o-question-mark-circle')
                    ->label('')
                    ->tooltip(__('resources/bankProfile/strings.form.tooltips.conversion_rate'))
            )
            ->validationAttribute(__('resources/bankProfile/strings.form.conversion_rate'));
    }

    public static function getCreationDateField()
    {
        return DatePicker::make('creation_date')
            ->label(__('resources/bankProfile/strings.form.creation_date'))
            ->native(false)
            ->adaptive()
            ->live(onBlur: true)
            ->validationAttribute(__('resources/bankProfile/strings.form.creation_date'));
    }

    public static function getCurrencyField(): Select
    {
        return Select::make('requested_currency_id')
            ->label(__('resources/bankProfile/strings.form.requested_currency'))
            ->relationship(
                name: 'requestedCurrency',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->columnSpan(1)
            ->default(fn($operation) => $operation === 'create' ? 2 : null) // 2 = EUR
            ->validationMessages([
                'required' => __('resources/bankProfile/strings.form.validation_required')
            ])
            ->afterStateUpdated(fn(Get $get, Set $set) => static::updateComputations($get, $set))
            ->validationAttribute(__('resources/bankProfile/strings.form.requested_currency'));
    }

    public static function getDeliveryDateField()
    {
        return DatePicker::make('delivery_date')
            ->label(__('resources/bankProfile/strings.form.delivery_date'))
            ->native(false)
            ->adaptive()
            ->afterOrEqual('purchase_date')
            ->validationMessages([
                'after_or_equal' => __('resources/bankProfile/strings.form.validation_date_after_or_equal', ['date' => __('resources/bankProfile/strings.form.purchase_date')]),
            ])
            ->helperText(__('resources/bankProfile/strings.form.helper_delivery_date'))
            ->validationAttribute(__('resources/bankProfile/strings.form.delivery_date'));
    }

    public static function getDocumentsAmountField(): TextInput
    {
        return TextInput::make('documents_amount')
            ->label(__('resources/bankProfile/strings.form.documents_amount'))
            ->numeric()
            ->minValue(0)
            ->live(onBlur: true)
            ->validationMessages([
                'numeric' => __('resources/bankProfile/strings.form.validation_numeric'),
                'min' => __('resources/bankProfile/strings.form.validation_min_numeric_zero'),
            ])
            ->hint(fn(Get $get) => is_numeric($get('documents_amount')) ? delimiter($get('documents_amount')) : $get('documents_amount'))
            ->afterStateUpdated(fn(Get $get, Set $set) => static::updateComputations($get, $set))
            ->validationAttribute(__('resources/bankProfile/strings.form.documents_amount'))
            ->helperText(__('resources/bankProfile/strings.form.helper_documents_amount'));
    }

    public static function getEurEquivalentRateField(): TextInput
    {
        return TextInput::make('eur_equivalent_rate')
            ->label(__('resources/bankProfile/strings.form.eur_equivalent_rate'))
            ->numeric()
            ->readOnly()
            ->validationMessages([
                'numeric' => __('resources/bankProfile/strings.form.validation_numeric'),
            ])
            ->hintAction(
                Action::make('help')
                    ->icon('heroicon-o-question-mark-circle')
                    ->label('')
                    ->tooltip(__('resources/bankProfile/strings.form.tooltips.eur_equivalent_rate'))
            )
            ->validationAttribute(__('resources/bankProfile/strings.form.eur_equivalent_rate'));
    }

    public static function getExchangeRateField(): TextInput
    {
        return TextInput::make('exchange_rate')
            ->label(__('resources/bankProfile/strings.form.exchange_rate'))
            ->numeric()
            ->minValue(0)
            ->live(onBlur: true)
            ->afterStateUpdated(fn(Get $get, Set $set) => static::updateComputations($get, $set))
            ->validationMessages([
                'numeric' => __('resources/bankProfile/strings.form.validation_numeric'),
                'min' => __('resources/bankProfile/strings.form.validation_min_numeric_zero'),
            ])
            ->hintAction(
                Action::make('help')
                    ->icon('heroicon-o-question-mark-circle')
                    ->label('')
                    ->tooltip(__('resources/bankProfile/strings.form.tooltips.exchange_rate'))
            )
            ->hint(fn(Get $get) => is_numeric($get('exchange_rate')) ? delimiter($get('exchange_rate')) : $get('exchange_rate'))
            ->validationAttribute(__('resources/bankProfile/strings.form.exchange_rate'));
    }

    public static function getNotesField(): Textarea
    {
        return Textarea::make('notes')
            ->label(__('resources/bankProfile/strings.form.notes'))
            ->rows(3)
            ->columnSpanFull()
            ->validationAttribute(__('resources/bankProfile/strings.form.notes'));
    }

    public static function getOrderNumberField(): TextInput
    {
        return TextInput::make('order_number')
            ->label(__('resources/bankProfile/strings.form.order_number'))
            ->maxLength(255)
            ->validationMessages([
                'max' => __('resources/bankProfile/strings.form.validation_max_length', ['max' => 255]),
            ])
            ->validationAttribute(__('resources/bankProfile/strings.form.order_number'));
    }

    public static function getCommitmentPaymentDateField()
    {
        return DatePicker::make('commitment_payment_date')
            ->label(__('resources/bankProfile/strings.form.commitment_payment_date'))
            ->native(false)
            ->adaptive()
            ->validationAttribute(__('resources/bankProfile/strings.form.commitment_payment_date'));
    }

    public static function getPaymentDueDateField()
    {
        return DatePicker::make('payment_due_date')
            ->label(__('resources/bankProfile/strings.form.payment_due_date'))
            ->native(false)
            ->adaptive()
            ->validationAttribute(__('resources/bankProfile/strings.form.payment_due_date'));
    }

    public static function getPurchaseDateField()
    {
        return DatePicker::make('purchase_date')
            ->label(__('resources/bankProfile/strings.form.purchase_date'))
            ->native(false)
            ->adaptive()
            ->afterOrEqual('allocation_date')
            ->validationMessages([
                'after_or_equal' => __('resources/bankProfile/strings.form.validation_date_after_or_equal', ['date' => __('resources/bankProfile/strings.form.allocation_date')]),
            ])
            ->validationAttribute(__('resources/bankProfile/strings.form.purchase_date'));
    }

    public static function getPurchasedCurrencyField(): Select
    {
        return Select::make('purchased_currency_id')
            ->label(__('resources/bankProfile/strings.form.purchased_currency'))
            ->relationship(
                name: 'purchasedCurrency',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->columnSpan(1)
            ->default(fn($operation) => $operation === 'create' ? 1 : null) // 2 = EUR
            ->validationMessages([
                'required' => __('resources/bankProfile/strings.form.validation_required')
            ])
            ->afterStateUpdated(fn(Get $get, Set $set) => static::updateComputations($get, $set))
            ->validationAttribute(__('resources/bankProfile/strings.form.purchased_currency'));
    }

    public static function getPurchasedEquivalentField(): TextInput
    {
        return TextInput::make('purchased_equivalent')
            ->label(__('resources/bankProfile/strings.form.purchased_equivalent'))
            ->numeric()
            ->minValue(0)
            ->live(onBlur: true)
            ->columnSpan(2)
            ->afterStateUpdated(fn(Get $get, Set $set) => static::updateComputations($get, $set))
            ->validationMessages([
                'numeric' => __('resources/bankProfile/strings.form.validation_numeric'),
                'min' => __('resources/bankProfile/strings.form.validation_min_numeric_zero'),
            ])
            ->hint(fn(Get $get) => is_numeric($get('purchased_equivalent')) ? delimiter($get('purchased_equivalent')) : $get('purchased_equivalent'))
            ->validationAttribute(__('resources/bankProfile/strings.form.purchased_equivalent'));
    }

    public static function getRegisteredOrderField(): Select
    {
        return Select::make('registered_order_id')
            ->label(__('resources/bankProfile/strings.form.registered_order'))
            ->relationship(name: 'registeredOrder')
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->formatted_name_without_date)
            ->live()
            ->afterStateUpdated(fn($state, Set $set) => $set(
                'order_number', RegisteredOrder::find($state)?->official_registration_no
            ))
            ->searchable()
            ->columns(1)
            ->preload()
            ->required()
            ->validationMessages([
                'required' => __('resources/bankProfile/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/bankProfile/strings.form.registered_order'));
    }

    public static function getRequestedAmountField(): TextInput
    {
        return TextInput::make('requested_amount')
            ->label(__('resources/bankProfile/strings.form.requested_amount'))
            ->numeric()
            ->required()
            ->minValue(0)
            ->columnSpan(2)
            ->live(onBlur: true)
            ->afterStateUpdated(fn(Get $get, Set $set) => static::updateComputations($get, $set))
            ->validationMessages([
                'numeric' => __('resources/bankProfile/strings.form.validation_numeric'),
                'min' => __('resources/bankProfile/strings.form.validation_min_numeric_zero'),
                'required' => __('resources/bankProfile/strings.form.validation_required'),
            ])
            ->hint(fn(Get $get) => is_numeric($get('requested_amount')) ? delimiter($get('requested_amount')) : $get('requested_amount'))
            ->helperText(__('resources/bankProfile/strings.form.helper_requested_amount'))
            ->validationAttribute(__('resources/bankProfile/strings.form.requested_amount'));
    }

    public static function getStatusField(): Select
    {
        return Select::make('status_id')
            ->label(__('resources/bankProfile/strings.form.status'))
            ->relationship(
                name: 'status',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
                modifyQueryUsing: fn($query) => $query->where('english_type', BankProfile::TYPE_BANK_PROFILE)
            )
            ->default(fn($operation): ?int => $operation === 'create' ? Status::findBy('Bank Profile Status', 'Submitted')?->id : null)
            ->searchable()
            ->preload()
            ->required()
            ->validationMessages([
                'required' => __('resources/bankProfile/strings.form.validation_required'),
            ])
            ->validationAttribute(__('resources/bankProfile/strings.form.status'));
    }

    public static function getSummaryFields(): array
    {
        return [
            TextEntry::make('commission_amount_purchased')
                ->label(__('resources/bankProfile/strings.form.summary_commission_amount'))
                ->formatStateUsing(fn(Get $get) => '💰 ' . number_format(static::computeCommissionAmount($get), 2)),
            TextEntry::make('commission_equivalent')
                ->label(__('resources/bankProfile/strings.form.summary_commission_equivalent'))
                ->formatStateUsing(fn(Get $get) => '💶 ' . number_format(static::computeCommissionEquivalent($get), 2)),
            TextEntry::make('total_purchased_remittance')
                ->label(__('resources/bankProfile/strings.form.summary_total_purchased'))
                ->formatStateUsing(fn(Get $get) => '💵 ' . number_format(static::computeTotalPurchasedRemittance($get), 2)),
            TextEntry::make('total_requested_remittance')
                ->label(__('resources/bankProfile/strings.form.summary_total_requested'))
                ->formatStateUsing(fn(Get $get) => '💶 ' . number_format(static::computeTotalRequestedRemittance($get), 2)),
            TextEntry::make('final_rate_display')
                ->label(__('resources/bankProfile/strings.form.final_rate'))
                ->formatStateUsing(fn(Get $get) => '💹 ' . number_format((float)$get('final_rate'), 2)),
            TextEntry::make('final_equivalent')
                ->label(__('resources/bankProfile/strings.form.summary_final_equivalent'))
                ->formatStateUsing(fn(Get $get) => '💶 ' . number_format(static::computeFinalEquivalent($get), 2)),
            TextEntry::make('total_rial_remittance')
                ->label(__('resources/bankProfile/strings.form.summary_total_rial'))
                ->columnSpanFull()
                ->formatStateUsing(fn(Get $get) => '🇮🇷 ' . number_format(static::computeTotalRialRemittance($get), 2)),
            TextEntry::make('remaining_commitment')
                ->label(__('resources/bankProfile/strings.form.summary_remaining'))
                ->formatStateUsing(fn(Get $get) => '📦 ' . number_format(static::computeRemaining($get), 2)),
        ];
    }

    public static function getSupplySourceField(): Select
    {
        return Select::make('supply_source')
            ->label(__('resources/bankProfile/strings.form.supply_source'))
            ->live()
            ->options(fn() => __('resources/bankProfile/strings.general.supply_sources'));
    }

    public static function getTargetableField(): MorphToSelect
    {
        return MorphToSelect::make('targetable')
            ->label(__('resources/bankProfile/strings.form.targetable'))
            ->types([
                Type::make(Category::class)
                    ->label(__('resources/bankProfile/strings.form.targetable_category'))
                    ->titleAttribute(app()->isLocale('fa') ? 'name' : 'english_name')
                    ->searchColumns(['name', 'english_name']),
                Type::make(Product::class)
                    ->label(__('resources/bankProfile/strings.form.targetable_product'))
                    ->getOptionLabelFromRecordUsing(fn(Product $r): string => $r->customized_label)
                    ->searchColumns(['code', 'name', 'english_name'])
            ])
            ->searchable()
            ->columns(1)
            ->required();
    }

    public static function getWaitingDurationField(): TextEntry
    {
        return TextEntry::make('waiting_duration')
            ->label(__('resources/bankProfile/strings.form.waiting_duration'))
            ->columnSpanFull()
            ->state(function (Get $get): string {
                $days = static::computeWaitingDuration($get);
                if ($days === null) return '-';

                return $days . ' ' . __('resources/bankProfile/strings.form.days');
            });
    }

    protected static function getFinalRateField(): Hidden
    {
        return Hidden::make('final_rate')
            ->live()
            ->dehydrated()
            ->afterStateUpdated(fn(Get $get, Set $set) => $set('final_rate_display',
                (float)($get('exchange_rate') ?? 0) * (1 + ((float)($get('commission_rate') ?? 0) / 100))
            ));
    }
}
