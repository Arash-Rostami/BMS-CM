<?php

namespace App\Filament\Resources\Operational\CustomResource\Traits;

use App\Models\Custom;
use App\Models\Shipment;
use App\Services\CodeGenerator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;

trait Form
{

    public static function getBankGuaranteeStatusField(): Select
    {
        return Select::make('bank_guarantee_status_id')
            ->label(__('resources/custom/strings.form.bank_guarantee_status'))
            ->relationship(
                name: 'bankGuaranteeStatus',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
                modifyQueryUsing: fn($query) => $query->where('english_type', Custom::TYPE_BANK_GUARANTEE_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getClearanceDateField()
    {
        return maybeJalali(DatePicker::make('clearance_date')
            ->label(__('resources/custom/strings.form.clearance_date'))
            ->native(false));
    }

    public static function getClearanceStatusField(): Select
    {
        return Select::make('clearance_status_id')
            ->label(__('resources/custom/strings.form.clearance_status'))
            ->relationship(
                name: 'clearanceStatus',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
                modifyQueryUsing: fn($query) => $query->where('english_type', Custom::TYPE_CLEARANCE_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getClearanceTypeField(): Select
    {
        return Select::make('clearance_type')
            ->label(__('resources/custom/strings.form.clearance_type'))
            ->options(['90_percent' => '90%', '10_percent' => '10%'])
            ->native(false)
            ->searchable()
            ->preload();
    }

    public static function getCommitmentBalanceField(): TextInput
    {
        return TextInput::make('commitment_balance')
            ->label(__('resources/custom/strings.form.commitment_balance'))
            ->numeric()
            ->prefix('💰')
            ->hint(fn(Get $get) => delimiter($get('commitment_balance')))
            ->validationMessages([
                'numeric' => __('resources/custom/strings.form.validation_numeric'),
            ]);
    }

    public static function getCommitmentPaymentDateField()
    {
        return maybeJalali(DatePicker::make('commitment_payment_date')
            ->label(__('resources/custom/strings.form.commitment_payment_date'))
            ->native(false));
    }

    public static function getCommitmentStatusField(): Select
    {
        return Select::make('commitment_status_id')
            ->label(__('resources/custom/strings.form.commitment_status'))
            ->relationship(
                name: 'commitmentStatus',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
                modifyQueryUsing: fn($query) => $query->where('english_type', Custom::TYPE_COMMITMENT_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getContractNoField(): TextInput
    {
        return TextInput::make('contract_no')
            ->label(__('resources/custom/strings.form.contract_no'))
            ->maxLength(255)
            ->readOnly()
            ->dehydrated();
    }

    public static function getCustomNoField(): TextInput
    {
        return TextInput::make('custom_no')
            ->label(__('resources/custom/strings.form.custom_no'))
            ->required()
            ->readOnly()
            ->unique(ignoreRecord: true)
            ->default(fn($operation) => $operation == 'create' ? CodeGenerator::generate('custom_no') : null)
            ->validationMessages([
                'required' => __('resources/custom/strings.form.validation_required'),
                'unique' => __('resources/custom/strings.form.validation_unique'),
            ]);
    }

    public static function getDeclarationNoField(): TextInput
    {
        return TextInput::make('declaration_no')
            ->label(__('resources/custom/strings.form.declaration_no'))
            ->maxLength(255);
    }

    public static function getDocSubmissionDateField()
    {
        return maybeJalali(DatePicker::make('doc_submission_date')
            ->label(__('resources/custom/strings.form.doc_submission_date'))
            ->native(false));
    }

    public static function getNotesField(): Textarea
    {
        return Textarea::make('notes')
            ->label(__('resources/custom/strings.form.notes'))
            ->rows(3)
            ->columnSpanFull();
    }

    public static function getPaymentDueDateField()
    {
        return maybeJalali(DatePicker::make('payment_due_date')
            ->label(__('resources/custom/strings.form.payment_due_date'))
            ->native(false));
    }

    public static function getRegisteredOrderField(): Select
    {
        return Select::make('registered_order_id')
            ->label(__('resources/custom/strings.form.registered_order'))
            ->relationship('registeredOrder', 'ro_number')
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->formatted_name_without_date)
            ->disabled()
            ->dehydrated()
            ->live()
            ->required()
            ->validationMessages([
                'required' => __('resources/custom/strings.form.validation_required'),
            ]);
    }

    public static function getRialReturnDateField()
    {
        return maybeJalali(DatePicker::make('rial_return_date')
            ->label(__('resources/custom/strings.form.rial_return_date'))
            ->native(false));
    }

    public static function getShipmentField(): Select
    {
        return Select::make('shipment_id')
            ->label(__('resources/custom/strings.form.shipment'))
            ->relationship('shipment', 'shipment_no')
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->formatted_name)
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->afterStateUpdated(function ($state, Set $set) {
                if ($record = Shipment::find($state)) {
                    $set('registered_order_id', $record->registered_order_id);
                    $set('contract_no', $record->contract_no);
                }
            })
            ->validationMessages([
                'required' => __('resources/custom/strings.form.validation_required'),
            ]);
    }

    public static function getTenPercentExitDateField()
    {
        return maybeJalali(DatePicker::make('ten_percent_exit_date')
            ->label(__('resources/custom/strings.form.ten_percent_exit_date'))
            ->native(false));
    }
}
