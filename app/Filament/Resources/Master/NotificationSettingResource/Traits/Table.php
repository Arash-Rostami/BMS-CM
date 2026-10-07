<?php

namespace App\Filament\Resources\Master\NotificationSettingResource\Traits;

use App\Jobs\ExportNotificationSettings;
use App\Models\NotificationSetting;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Support\Collection;

trait Table
{
    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportNotificationSettings')
            ->label(__('resources/notificationSetting/strings.export.export_notification_settings'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => static::canViewAny())
            ->action(function (Collection $records): void {
                ExportNotificationSettings::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function showActions(): TextColumn
    {
        return TextColumn::make('settings.actions')
            ->label(__('resources/notificationSetting/strings.table.actions'))
            ->badge()
            ->listWithLineBreaks()
            ->getStateUsing(fn ($record) => $record->getLocalizedActions())
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false);
    }

    public static function showColumnValues(): TextColumn
    {
        return TextColumn::make('settings.values')
            ->label(__('resources/notificationSetting/strings.table.column_values'))
            ->sortable()
            ->listWithLineBreaks()
            ->badge()
            ->toggleable(isToggledHiddenByDefault: true)
            ->limit(50);
    }

    public static function showColumns(): TextColumn
    {
        return TextColumn::make('settings.columns')
            ->label(__('resources/notificationSetting/strings.table.columns'))
            ->sortable()
            ->searchable()
            ->badge()
            ->listWithLineBreaks()
            ->toggleable(isToggledHiddenByDefault: true)
            ->getStateUsing(fn ($record) => array_keys(
                NotificationSetting::getColumnValuesForSelectedColumns(
                    $record->getColumns() ?? [], $record->getTables() ?? [])
            ));
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/notificationSetting/strings.table.created_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/notificationSetting/strings.table.created_by'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showIsActive(): ToggleColumn
    {
        return ToggleColumn::make('settings.is_active')
            ->label(__('resources/notificationSetting/strings.table.is_active'))
            ->onIcon('heroicon-o-check-circle')
            ->offIcon('heroicon-o-x-circle')
            ->onColor('success')
            ->offColor('danger')
            ->toggleable(isToggledHiddenByDefault: false)
            ->sortable();
    }

    public static function showNotificationChannel(): TextColumn
    {
        return TextColumn::make('notification_type')
            ->label(__('resources/notificationSetting/strings.table.notification_type'))
            ->sortable()
            ->getStateUsing(fn ($record) => $record->notification_channel)
            ->toggleable(isToggledHiddenByDefault: false);
    }

    public static function showRecipients(): TextColumn
    {
        return TextColumn::make('recipient.*.name')
            ->label(__('resources/notificationSetting/strings.table.users'))
            ->badge()
            ->listWithLineBreaks()
            ->getStateUsing(function ($record) {
                $names = static::recipientNames();

                return collect($record->getUsers())->map(fn ($id) => $names[$id] ?? null)->filter()->values()->all();
            })
            ->toggleable(isToggledHiddenByDefault: false);
    }

    protected static function recipientNames(): array
    {
        $attributes = request()->attributes;

        if (! $attributes->has('notification_setting.recipient_names')) {
            $attributes->set('notification_setting.recipient_names', User::pluck('name', 'id')->all());
        }

        return $attributes->get('notification_setting.recipient_names');
    }

    public static function showTable(): TextColumn
    {
        return TextColumn::make('settings.tables')
            ->label(__('resources/notificationSetting/strings.table.tables'))
            ->getStateUsing(fn ($record) => $record->getLocalizedTables())
            ->sortable()
            ->badge()
            ->listWithLineBreaks()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/notificationSetting/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/notificationSetting/strings.table.updated_by'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
