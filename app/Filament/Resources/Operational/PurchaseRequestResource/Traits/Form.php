<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Traits;

use App\Models\Status;
use App\Models\User;
use App\Services\CodeGenerator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;


trait Form
{
    public static function getApprovalDateField(): DatePicker
    {
        return DatePicker::make('approval_date')
            ->label(__('resources/purchaseRequest/strings.form.approval_date'))
            ->adaptive()
            ->disabled()
            ->validationAttribute(__('resources/purchaseRequest/strings.form.approval_date'));
    }

    public static function getApproverIdField(): Select
    {
        return Select::make('approver_id')
            ->label(__('resources/purchaseRequest/strings.form.approver'))
            ->relationship('approver', 'name')
            ->getOptionLabelFromRecordUsing(fn(User $record) => $record->name ?? '--')
            ->disabled()
            ->validationAttribute(__('resources/purchaseRequest/strings.form.approver'));

    }

    public static function getCostCenterIdField(): Select
    {
        $department = auth()->user()?->department;

        return Select::make('cost_center_id')
            ->label(__('resources/purchaseRequest/strings.form.cost_center'))
            ->relationship('costCenter', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->getOptionLabelFromRecordUsing(fn(?Model $record) => $record->getLocalizedNameAttribute() ?? '--')
            ->default($department?->id)
            ->searchable()
            ->preload()
            ->required()
            ->validationMessages([
                'required' => __('resources/purchaseRequest/strings.form.validation_department_required'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.cost_center'));

    }

    public static function getItemEstimatedCostField(): TextInput
    {
        return TextInput::make('estimated_cost')
            ->label(__('resources/purchaseRequest/strings.form.estimated_cost'))
            ->prefix('💰')
            ->columns(1)
            ->numeric()
            ->hint(fn(Get $get) => delimiter($get('estimated_cost')))
            ->default(0)
            ->minValue(0)
            ->step(0.01)
            ->live(onBlur: true)
            ->helperText(__('resources/purchaseRequest/strings.form.helper_estimated_cost'))
            ->validationMessages([
                'required' => __('resources/purchaseRequest/strings.form.validation_estimated_cost_required'),
                'numeric' => __('resources/purchaseRequest/strings.form.validation_estimated_cost_numeric'),
                'min' => __('resources/purchaseRequest/strings.form.validation_estimated_cost_min'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.estimated_cost'));
    }

    public static function getItemNotesField(): Textarea
    {
        return Textarea::make('notes')
            ->hiddenLabel()
            ->maxLength(255)
            ->reactive()
            ->extraAttributes(fn(Get $get) => ['style' => !$get('show_notes') ? 'display: none;' : ''])
            ->columnSpan('full')
            ->rules(['max:500'])
            ->validationMessages([
                'max' => __('resources/purchaseRequest/strings.form.validation_item_notes_max'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.item_notes'));
    }

    public static function getItemNotesToggle(): Toggle
    {
        return Toggle::make('show_notes')
            ->label(__('resources/purchaseRequest/strings.form.add_notes'))
            ->onIcon('heroicon-s-check-circle')
            ->offIcon('heroicon-s-x-circle')
            ->onColor('success')
            ->offColor('danger')
            ->reactive()
            ->afterStateHydrated(fn(Set $set, Get $get) => $set('show_notes', filled($get('notes'))))
            ->afterStateUpdated(fn(?bool $state, Set $set) => !$state ? $set('notes', null) : null);
    }

    public static function getItemProductIdField(): Select
    {
        return Select::make('product_id')
            ->label(__('resources/purchaseRequest/strings.form.product'))
            ->relationship('product', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->getCustomizedLabelAttribute() ?? $record->slug ?? '-')
            ->searchable(['name', 'english_name', 'code'])
            ->preload()
            ->columnSpan(2)
            ->required()
            ->live()
            ->disableOptionWhen(fn($value, Get $get) => in_array($value, collect($get('../../items'))
                ->pluck('product_id')
                ->filter()
                ->diff([$get('product_id')])
                ->toArray()))
            ->validationMessages([
                'required' => __('resources/purchaseRequest/strings.form.validation_product_required'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.product'));
    }

    public static function getItemQuantityField(): TextInput
    {
        return TextInput::make('quantity')
            ->label(__('resources/purchaseRequest/strings.form.quantity'))
            ->numeric()
            ->hint(fn(Get $get) => delimiter($get('quantity')))
            ->required()
            ->default(0)
            ->numeric()
            ->required()
            ->minValue(0.01)
            ->step(0.01)
            ->live(onBlur: true)
            ->helperText(__('resources/purchaseRequest/strings.form.helper_quantity'))
            ->validationMessages([
                'required' => __('resources/purchaseRequest/strings.form.validation_quantity_required'),
                'numeric' => __('resources/purchaseRequest/strings.form.validation_quantity_numeric'),
                'min' => __('resources/purchaseRequest/strings.form.validation_quantity_min'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.quantity'));
    }

// Purchase Item Fields

    public static function getItemStatusIdField(): Select
    {
        return Select::make('status_id')
            ->label(__('resources/purchaseRequest/strings.form.status'))
            ->relationship('status', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->default(fn($operation): ?int => $operation === 'create' ? Status::findBy('Purchase Item Status', 'Under Review')?->id : null)
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->getLocalizedNameAttribute() ?? '--')
            ->columns(1)
            ->required()
            ->validationMessages([
                'required' => __('resources/purchaseRequest/strings.form.validation_status_required'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.status'));
    }

    public static function getItemUnitField(): Select
    {
        return Select::make('unit')
            ->label(__('resources/target/strings.form.metrics'))
            ->options(__('resources/target/strings.metrics'))
            ->searchable()
            ->preload()
            ->columns(1)
            ->nullable();
    }

    public static function getNotesField(): RichEditor
    {
        return RichEditor::make('notes')
            ->label(__('resources/purchaseRequest/strings.form.notes'))
            ->maxLength(65535)
            ->rules(['max:65535'])
            ->toolbarButtons(['bold', 'underline', 'link', 'bulletList', 'orderedList'])
            ->validationMessages([
                'max' => __('resources/purchaseRequest/strings.form.validation_max'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.notes'));
    }

    public static function getPrNumberField(): TextInput
    {
        return TextInput::make('pr_number')
            ->label(__('resources/purchaseRequest/strings.form.pr_number'))
            ->required()
            ->readOnly()
            ->maxLength(255)
            ->unique(ignoreRecord: true)
            ->default(fn($operation) => $operation == 'create' ? CodeGenerator::generate('pr_number') : null)
            ->validationMessages([
                'required' => __('resources/purchaseRequest/strings.form.validation_required'),
                'unique' => __('resources/purchaseRequest/strings.form.validation_unique'),
                'max' => __('resources/purchaseRequest/strings.form.validation_max_string'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.pr_number'));
    }

    public static function getRejectionReasonField(): Textarea
    {
        return Textarea::make('rejection_reason')
            ->label(__('resources/purchaseRequest/strings.form.rejection_reason'))
            ->rows(3)
            ->visible(fn(Get $get): bool => ($statusId = $get('status_id')) && Status::where('id', $statusId)->value('english_name') === 'Declined')
            ->maxLength(65535)
            ->rules(['max:65535'])
            ->validationMessages([
                'max' => __('resources/purchaseRequest/strings.form.validation_max'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.rejection_reason'));
    }

    public static function getRequiredByDateField(): DatePicker
    {
        return DatePicker::make('required_by_date')
            ->label(__('resources/purchaseRequest/strings.form.required_by_date'))
            ->adaptive()
            ->native(false)
            ->required()
            ->rules(['after:today'])
            ->helperText(__('resources/purchaseRequest/strings.form.helper_required_by_date'))
            ->validationMessages([
                'required' => __('resources/purchaseRequest/strings.form.validation_required_by_date_required'),
                'after' => __('resources/purchaseRequest/strings.form.validation_required_by_date_after'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.required_by_date'));
    }

    public static function getStatusIdField(): Select
    {
        return Select::make('status_id')
            ->label(__('resources/purchaseRequest/strings.form.status'))
            ->relationship(
                name: 'status',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->default(fn($operation): ?int => $operation === 'create' ? Status::findBy('Purchase Request Status', 'Under Review')?->id : null)
            ->live()
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->getLocalizedNameAttribute() ?? '--')
            ->required()
            ->validationMessages([
                'required' => __('resources/purchaseRequest/strings.form.validation_status_required'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.status'));
    }

    public static function getTotalEstimatedCostField(): TextInput
    {
        return TextInput::make('total_estimated_cost')
            ->label(__('resources/purchaseRequest/strings.form.total_estimated_cost'))
            ->live()
            ->dehydrateStateUsing(fn($state) => floatval(str_replace(['💰', ',', ' '], '', $state)))
            ->formatStateUsing(fn(Get $get) => is_numeric($get('total_estimated_cost')) ? '💰 ' . number_format($get('total_estimated_cost'), 2) : $get('total_estimated_cost'));
    }

    public static function getUrgencyLevelField(): Select
    {
        return Select::make('urgency_level')
            ->label(__('resources/purchaseRequest/strings.form.urgency_level'))
            ->options(__('resources/purchaseRequest/strings.general.urgency'))
            ->required()
            ->validationMessages([
                'required' => __('resources/purchaseRequest/strings.form.validation_urgency_required'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.urgency_level'))
            ->helperText(__('resources/purchaseRequest/strings.form.helper_urgency_level'));
    }
}
