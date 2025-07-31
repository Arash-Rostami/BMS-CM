<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Traits;

use App\Models\Status;
use App\Models\User;
use App\Services\FileUploadManager;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;


trait Form
{
    // Main Form Fields
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

    public static function getRequiredByDateField(): DatePicker
    {
        return DatePicker::make('required_by_date')
            ->label(__('resources/purchaseRequest/strings.form.required_by_date'))
            ->when(app()->isLocale('fa'), fn($column) => $column->jalali())
            ->native(false)
            ->required()
            ->rules(['after:today'])
            ->validationMessages([
                'required' => __('resources/purchaseRequest/strings.form.validation_required_by_date_required'),
                'after' => __('resources/purchaseRequest/strings.form.validation_required_by_date_after'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.required_by_date'));
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
            ->validationAttribute(__('resources/purchaseRequest/strings.form.urgency_level'));
    }

    public static function getTotalEstimatedCostField(): TextInput
    {
        return TextInput::make('total_estimated_cost')
            ->label(__('resources/purchaseRequest/strings.form.total_estimated_cost'))
            ->nullable()
            ->readOnly()
            ->placeholder('🔒')
            ->prefix('💰');
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

    public static function getApproverIdField(): Select
    {
        return Select::make('approver_id')
            ->label(__('resources/purchaseRequest/strings.form.approver'))
            ->relationship('approver', 'name')
            ->getOptionLabelFromRecordUsing(fn(User $record) => $record->name ?? '--')
            ->disabled()
            ->validationAttribute(__('resources/purchaseRequest/strings.form.approver'));

    }

    public static function getApprovalDateField(): DatePicker
    {
        return DatePicker::make('approval_date')
            ->label(__('resources/purchaseRequest/strings.form.approval_date'))
            ->when(app()->isLocale('fa'), fn($column) => $column->jalali())
            ->disabled()
            ->validationAttribute(__('resources/purchaseRequest/strings.form.approval_date'));
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


    public static function getNotesField(): Textarea
    {
        return Textarea::make('notes')
            ->label(__('resources/purchaseRequest/strings.form.notes'))
            ->rows(3)
            ->maxLength(65535)
            ->rules(['max:65535'])
            ->validationMessages([
                'max' => __('resources/purchaseRequest/strings.form.validation_max'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.notes'));
    }

    public static function getAttachmentsField(): FileUpload
    {
        return FileUpload::make('attachments')
            ->label(__('resources/purchaseRequest/strings.form.attachments'))
            ->multiple()
            ->disk('public')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'application/pdf', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',])
            ->maxSize(2500)
            ->previewable()
            ->openable()
            ->downloadable()
            ->hintIconTooltip(fn($record) => $record?->attachments()->latest('id')->implode('name', "\n") ?? '')
            ->validationMessages([
                'accepted' => __('resources/purchaseRequest/strings.form.validation_attachments_type'),
                'max_size' => __('resources/purchaseRequest/strings.form.validation_attachments_size'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.attachments'))
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

// Purchase Item Fields
    public static function getItemProductIdField(): Select
    {
        return Select::make('product_id')
            ->label(__('resources/purchaseRequest/strings.form.product'))
            ->relationship('product', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->getLocalizedNameAttribute() ?? $record->slug ?? '-')
            ->searchable(['name', 'english_name', 'code'])
            ->preload()
            ->columnSpan(2)
            ->required()
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
            ->required()
            ->default(0)
            ->numeric()
            ->required()
            ->minValue(0.01)
            ->step(0.01)
            ->live(onBlur: true)
            ->validationMessages([
                'required' => __('resources/purchaseRequest/strings.form.validation_quantity_required'),
                'numeric' => __('resources/purchaseRequest/strings.form.validation_quantity_numeric'),
                'min' => __('resources/purchaseRequest/strings.form.validation_quantity_min'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.quantity'));
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

    public static function getItemEstimatedCostField(): TextInput
    {
        return TextInput::make('estimated_cost')
            ->label(__('resources/purchaseRequest/strings.form.estimated_cost'))
            ->prefix('💰')
            ->columns(1)
            ->numeric()
            ->required()
            ->minValue(0)
            ->step(0.01)
            ->live(onBlur: true)
            ->validationMessages([
                'required' => __('resources/purchaseRequest/strings.form.validation_estimated_cost_required'),
                'numeric' => __('resources/purchaseRequest/strings.form.validation_estimated_cost_numeric'),
                'min' => __('resources/purchaseRequest/strings.form.validation_estimated_cost_min'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.estimated_cost'));
    }

    public static function getItemStatusIdField(): Select
    {
        return Select::make('status_id')
            ->label(__('resources/purchaseRequest/strings.form.status'))
            ->relationship('status', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->default(fn($operation): ?int => $operation === 'create' ? Status::findBy('Purchase Item Status', 'Under Review')?->id : null)
            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->getLocalizedNameAttribute() ?? '--')
            ->columns(1)
            ->required();
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

    public static function getItemNotesField(): Textarea
    {
        return Textarea::make('notes')
            ->label('')
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

    public
    static function getItemAttachmentsToggle(): Toggle
    {
        return Toggle::make('has_item_attachments')
            ->label(__('resources/purchaseRequest/strings.form.add_item_attachments'))
            ->reactive()
            ->afterStateHydrated(fn(?Model $record, Set $set) => $set('has_item_attachments', $record?->attachments()->exists()))
            ->dehydrated(false)
            ->disabled(fn(?Model $record) => $record?->attachments()->exists());
    }

    public static function getItemAttachmentsField(): FileUpload
    {
        return FileUpload::make('attachments')
            ->label(__('resources/purchaseRequest/strings.form.attachments'))
            ->multiple()
            ->disk('public')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'application/pdf', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',])
            ->maxSize(2500)
            ->previewable()
            ->openable()
            ->downloadable()
            ->columnSpan('full')
            ->hintIcon('heroicon-o-question-mark-circle')
            ->hintIconTooltip(fn($record) => $record?->attachments()->latest('id')->implode('name', "\n") ?? '')
            ->visible(fn(Get $get) => $get('has_item_attachments'))
            ->validationMessages([
                'accepted' => __('resources/purchaseRequest/strings.form.validation_item_attachments_type'),
                'max_size' => __('resources/purchaseRequest/strings.form.validation_item_attachments_size'),
            ])
            ->validationAttribute(__('resources/purchaseRequest/strings.form.item_attachments'))
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
}
