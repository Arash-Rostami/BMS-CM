<?php

namespace App\Filament\Resources\Operational\CustomResource\Traits;

use App\Models\Custom;
use App\Models\Shipment;
use App\Models\Status;
use App\Services\CodeGenerator;
use App\Services\StatusWorkflow;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;

trait Form
{
    protected static function getWorkflowStatusField(string $column, string $relation, string $type, string $label): Select
    {
        return Select::make($column)
            ->label($label)
            ->relationship(
                name: $relation,
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
                modifyQueryUsing: fn ($query, ?Model $record) => $query->whereIn('id', static::availableWorkflowStatusIds($type, $relation, $record)),
            )
            ->default(fn ($operation): ?int => $operation === 'create' ? StatusWorkflow::initialFor($type)?->id : null)
            ->live()
            ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->getLocalizedNameAttribute() ?? '--')
            ->disableOptionWhen(fn ($value, ?Model $record): bool => ! ($target = Status::find($value))
                || ! StatusWorkflow::canSet(auth()->user(), $target, $record?->{$relation}))
            ->searchable()
            ->preload()
            ->validationAttribute($label)
            ->validationMessages([
                'in' => __('resources/custom/strings.form.validation_exists'),
            ]);
    }

    protected static function availableWorkflowStatusIds(string $type, string $relation, ?Model $record): array
    {
        $current = $record?->{$relation};
        $ids = collect();

        if ($current) {
            $ids->push($current->id);
            $ids->push(StatusWorkflow::nextFor($current)?->id);
        } else {
            $ids->push(StatusWorkflow::initialFor($type)?->id);
        }

        return $ids->merge(Status::where('english_type', $type)->whereNull('stage_order')->pluck('id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function getBankGuaranteeStatusField(): Select
    {
        return static::getWorkflowStatusField(
            'bank_guarantee_status_id',
            'bankGuaranteeStatus',
            Custom::TYPE_BANK_GUARANTEE_STATUS,
            __('resources/custom/strings.form.bank_guarantee_status')
        );
    }

    public static function getClearanceDateField()
    {
        return DatePicker::make('clearance_date')
            ->label(__('resources/custom/strings.form.clearance_date'))
            ->native(false)
            ->adaptive()
            ->validationAttribute(__('resources/custom/strings.form.clearance_date'))
            ->validationMessages([
                'date' => __('resources/custom/strings.form.validation_date'),
            ]);
    }

    public static function getClearanceStatusField(): Select
    {
        return static::getWorkflowStatusField(
            'clearance_status_id',
            'clearanceStatus',
            Custom::TYPE_CLEARANCE_STATUS,
            __('resources/custom/strings.form.clearance_status')
        );
    }

    public static function getClearanceTypeField(): Select
    {
        return Select::make('clearance_type')
            ->label(__('resources/custom/strings.form.clearance_type'))
            ->options(__('resources/custom/strings.general.clearance_types'))
            ->native(false)
            ->searchable()
            ->preload()
            ->live()
            ->validationMessages([
                'in' => __('resources/custom/strings.form.validation_exists'),
            ])
            ->helperText(__('resources/custom/strings.form.helper_clearance_type'));
    }

    public static function getCommitmentBalanceField(): TextInput
    {
        return TextInput::make('commitment_balance')
            ->label(__('resources/custom/strings.form.commitment_balance'))
            ->numeric()
            ->live()
            ->prefix('💰')
            ->hint(fn (Get $get) => preciseNumber($get('commitment_balance')))
            ->validationAttribute(__('resources/custom/strings.form.commitment_balance'))
            ->validationMessages([
                'numeric' => __('resources/custom/strings.form.validation_numeric'),
            ])
            ->helperText(__('resources/custom/strings.form.helper_commitment_balance'));
    }

    public static function getCommitmentStatusField(): Select
    {
        return static::getWorkflowStatusField(
            'commitment_status_id',
            'commitmentStatus',
            Custom::TYPE_COMMITMENT_STATUS,
            __('resources/custom/strings.form.commitment_status')
        );
    }

    public static function getContractNoField(): TextInput
    {
        return TextInput::make('contract_no')
            ->label(__('resources/custom/strings.form.contract_no'))
            ->maxLength(255)
            ->readOnly()
            ->dehydrated()
            ->validationAttribute(__('resources/custom/strings.form.contract_no'))
            ->validationMessages([
                'max' => __('resources/custom/strings.form.validation_contract_no_max'),
            ]);
    }

    public static function getCustomNoField(): TextInput
    {
        return TextInput::make('custom_no')
            ->label(__('resources/custom/strings.form.custom_no'))
            ->helperText(__('resources/custom/strings.form.helper_custom_no'))
            ->required()
            ->readOnly()
            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->withoutTrashed())
            ->default(fn ($operation) => $operation == 'create' ? CodeGenerator::generate('custom_no') : null)
            ->validationAttribute(__('resources/custom/strings.form.custom_no'))
            ->validationMessages([
                'required' => __('resources/custom/strings.form.validation_required'),
                'unique' => __('resources/custom/strings.form.validation_unique'),
            ]);
    }

    public static function getDeclarationNoField(): TextInput
    {
        return TextInput::make('declaration_no')
            ->label(__('resources/custom/strings.form.declaration_no'))
            ->maxLength(255)
            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->withoutTrashed())
            ->validationAttribute(__('resources/custom/strings.form.declaration_no'))
            ->validationMessages([
                'max' => __('resources/custom/strings.form.validation_declaration_no_max'),
                'unique' => __('resources/custom/strings.form.validation_declaration_no_unique'),
            ])
            ->helperText(__('resources/custom/strings.form.helper_declaration_no'));
    }

    public static function getDocSubmissionDateField()
    {
        return DatePicker::make('doc_submission_date')
            ->label(__('resources/custom/strings.form.doc_submission_date'))
            ->native(false)
            ->adaptive()
            ->validationAttribute(__('resources/custom/strings.form.doc_submission_date'))
            ->validationMessages([
                'date' => __('resources/custom/strings.form.validation_date'),
            ]);
    }

    public static function getNotesField(): Textarea
    {
        return Textarea::make('notes')
            ->label(__('resources/custom/strings.form.notes'))
            ->rows(3)
            ->columnSpanFull();
    }

    public static function getRegisteredOrderField(): Select
    {
        return Select::make('registered_order_id')
            ->label(__('resources/custom/strings.form.registered_order'))
            ->relationship('registeredOrder', 'ro_number')
            ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->formatted_name_without_date)
            ->disabled()
            ->dehydrated()
            ->live()
            ->required()
            ->validationAttribute(__('resources/custom/strings.form.registered_order'))
            ->validationMessages([
                'required' => __('resources/custom/strings.form.validation_required'),
                'in' => __('resources/custom/strings.form.validation_exists'),
            ]);
    }

    public static function getRialReturnDateField()
    {
        return DatePicker::make('rial_return_date')
            ->label(__('resources/custom/strings.form.rial_return_date'))
            ->native(false)
            ->adaptive()
            ->visible(fn (Get $get) => $get('clearance_type') === 'percentage')
            ->validationAttribute(__('resources/custom/strings.form.rial_return_date'))
            ->validationMessages([
                'date' => __('resources/custom/strings.form.validation_date'),
            ]);
    }

    public static function getShipmentField(): Select
    {
        return Select::make('shipment_id')
            ->label(__('resources/custom/strings.form.shipment'))
            ->helperText(__('resources/custom/strings.form.helper_shipment'))
            ->relationship('shipment', 'shipment_no')
            ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->formatted_name)
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
            ->validationAttribute(__('resources/custom/strings.form.shipment'))
            ->validationMessages([
                'required' => __('resources/custom/strings.form.validation_required'),
                'in' => __('resources/custom/strings.form.validation_exists'),
            ]);
    }

    public static function getTenPercentExitDateField()
    {
        return DatePicker::make('ten_percent_exit_date')
            ->label(__('resources/custom/strings.form.ten_percent_exit_date'))
            ->native(false)
            ->adaptive()
            ->visible(fn (Get $get) => $get('clearance_type') === 'percentage')
            ->validationAttribute(__('resources/custom/strings.form.ten_percent_exit_date'))
            ->validationMessages([
                'date' => __('resources/custom/strings.form.validation_date'),
            ]);
    }
}
