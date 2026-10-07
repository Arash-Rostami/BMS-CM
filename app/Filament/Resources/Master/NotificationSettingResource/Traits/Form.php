<?php

namespace App\Filament\Resources\Master\NotificationSettingResource\Traits;

use App\Models\NotificationSetting;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

trait Form
{
    public static function getActionSelector(): Select
    {
        return Select::make('settings.actions')
            ->label(__('resources/notificationSetting/strings.form.actions'))
            ->options([
                'create' => __('resources/notificationSetting/strings.action_types.create'),
                'update' => __('resources/notificationSetting/strings.action_types.update'),
                'delete' => __('resources/notificationSetting/strings.action_types.delete'),
            ])
            ->default('create')
            ->live()
            ->multiple()
            ->required()
            ->columnSpan(1)
            ->columnSpanFull()
            ->validationMessages([
                'required' => __('resources/notificationSetting/strings.form.validation_required'),
                '*.in' => __('resources/notificationSetting/strings.form.validation_in'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.helper_actions'));
    }

    public static function getColumnSelector(): Select
    {
        return Select::make('settings.columns')
            ->label(__('resources/notificationSetting/strings.form.columns'))
            ->options(fn ($get) => NotificationSetting::getColumnsForSelectedTables($get('settings.tables')))
            ->multiple()
            ->columnSpan(1)
            ->columnSpanFull()
            ->live()
            ->searchable()
            ->disabled(fn ($get) => ! in_array('update', (array) $get('settings.actions')))
            ->hidden(fn ($get) => ! in_array('update', (array) $get('settings.actions')))
            ->nullable()
            ->validationMessages([
                '*.in' => __('resources/notificationSetting/strings.form.validation_in'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.helper_columns'));
    }

    public static function getColumnValueSelector()
    {
        return Select::make('settings.values')
            ->label(__('resources/notificationSetting/strings.form.column_values'))
            ->options(fn ($get) => NotificationSetting::getColumnValuesForSelectedColumns(
                $get('settings.columns') ?? [],
                $get('settings.tables') ?? []
            ))
            ->multiple()
            ->columnSpan(1)
            ->columnSpanFull()
            ->searchable()
            ->live()
            ->disabled(fn ($get) => ! in_array('update', (array) $get('settings.actions')))
            ->hidden(fn ($get) => ! in_array('update', (array) $get('settings.actions')))
            ->nullable()
            ->validationMessages([
                '*.in' => __('resources/notificationSetting/strings.form.validation_in'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.helper_column_values'));
    }

    public static function getIsActive(): Toggle
    {
        return Toggle::make('settings.is_active')
            ->label(__('resources/notificationSetting/strings.form.is_active'))
            ->default(true)
            ->inline(false)
            ->onIcon('heroicon-s-check-circle')
            ->offIcon('heroicon-s-x-circle')
            ->onColor('success')
            ->offColor('danger');
    }

    public static function getNotes(): Textarea
    {
        return Textarea::make('notes')
            ->label(__('resources/notificationSetting/strings.form.notes'))
            ->nullable()
            ->columnSpanFull()
            ->maxLength(500)
            ->validationAttribute(__('resources/notificationSetting/strings.form.notes'))
            ->validationMessages([
                'max' => __('resources/notificationSetting/strings.form.validation_notes_max'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.helper_notes'));
    }

    public static function getNotificationChannel(): Select
    {
        return Select::make('notification_type')
            ->label(__('resources/notificationSetting/strings.form.notification_type'))
            ->options(NotificationSetting::notificationChannel())
            ->default('in_app')
            ->required()
            ->columnSpan(1)
            ->columnSpanFull()
            ->validationMessages([
                'required' => __('resources/notificationSetting/strings.form.validation_required'),
                'in' => __('resources/notificationSetting/strings.form.validation_in'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.helper_notification_type'));
    }

    public static function getTableSelector(): Select
    {
        return Select::make('settings.tables')
            ->label(__('resources/notificationSetting/strings.form.tables'))
            ->options(NotificationSetting::getAvailableModels())
            ->multiple()
            ->required()
            ->columnSpan(1)
            ->columnSpanFull()
            ->searchable()
            ->validationMessages([
                'required' => __('resources/notificationSetting/strings.form.validation_required'),
                '*.in' => __('resources/notificationSetting/strings.form.validation_in'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.tables_description'));
    }

    public static function getUserSelector(): Select
    {
        return Select::make('settings.users')
            ->label(__('resources/notificationSetting/strings.form.users'))
            ->options(fn () => User::pluck('name', 'id'))
            ->default(fn () => [auth()->id()])
            ->columnSpan(1)
            ->multiple()
            ->required()
            ->columnSpanFull()
            ->searchable()
            ->validationMessages([
                'required' => __('resources/notificationSetting/strings.form.validation_required'),
                '*.in' => __('resources/notificationSetting/strings.form.validation_in'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.helper_users'));
    }
}
