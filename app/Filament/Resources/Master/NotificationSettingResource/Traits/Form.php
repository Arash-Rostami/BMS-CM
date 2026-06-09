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
                'create' => '🟢 Create',
                'update' => '🟡 Update',
                'delete' => '🔴 Delete',
            ])
            ->default('create')
            ->live()
            ->multiple()
            ->columnSpan(1)
            ->columnSpanFull()
            ->helperText(__('resources/notificationSetting/strings.form.helper_actions'));
    }

    public static function getColumnSelector(): Select
    {
        return Select::make('settings.columns')
            ->label(__('resources/notificationSetting/strings.form.columns'))
            ->options(fn($get) => NotificationSetting::getColumnsForSelectedTables($get('settings.tables')))
            ->multiple()
            ->columnSpan(1)
            ->columnSpanFull()
            ->live()
            ->searchable()
            ->disabled(fn($get) => !in_array('update', (array)$get('settings.actions')))
            ->hidden(fn($get) => !in_array('update', (array)$get('settings.actions')))
            ->nullable()
            ->helperText(__('resources/notificationSetting/strings.form.helper_columns'));
    }

    public static function getColumnValueSelector()
    {
        return Select::make('settings.values')
            ->label(__('resources/notificationSetting/strings.form.column_values'))
            ->options(fn($get) => NotificationSetting::getColumnValuesForSelectedColumns(
                $get('settings.columns') ?? [],
                $get('settings.tables') ?? []
            ))
            ->multiple()
            ->columnSpan(1)
            ->columnSpanFull()
            ->searchable()
            ->live()
            ->disabled(fn($get) => !in_array('update', (array)$get('settings.actions')))
            ->hidden(fn($get) => !in_array('update', (array)$get('settings.actions')))
            ->nullable()
            ->helperText(__('resources/notificationSetting/strings.form.helper_column_values'));
    }

    public static function getDescription(): Textarea
    {
        return Textarea::make('description')
            ->label(__('resources/notificationSetting/strings.form.description'))
            ->maxLength(65535)
            ->nullable();
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
            ->columnSpan(1)
            ->columnSpanFull();
    }

    public static function getTableSelector(): Select
    {
        return Select::make('settings.tables')
            ->label(__('resources/notificationSetting/strings.form.tables'))
            ->options(NotificationSetting::getAvailableModels())
            ->multiple()
            ->columnSpan(1)
            ->columnSpanFull()
            ->searchable()
            ->nullable()
            ->helperText(__('resources/notificationSetting/strings.form.tables_description'));
    }

    public static function getUserSelector(): Select
    {
        return Select::make('settings.users')
            ->label(__('resources/notificationSetting/strings.form.users'))
            ->options(User::all()->pluck('name', 'id'))
            ->columnSpan(1)
            ->multiple()
            ->columnSpanFull()
            ->searchable()
            ->nullable();
    }
}
