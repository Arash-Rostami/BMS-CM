<?php

namespace App\Filament\Resources\Master\NotificationSettingResource\Traits;

use App\Models\NotificationSetting;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Arr;

trait Infolist
{
    public static function viewActions(): TextEntry
    {
        return TextEntry::make('settings.actions')
            ->label(__('resources/notificationSetting/strings.infolist.actions'))
            ->badge()
            ->listWithLineBreaks()
            ->getStateUsing(fn($record) => collect($record->getActions())->map(fn($action) => match ($action) {
                'create' => '🟢 Create',
                'update' => '🟡 Update',
                'delete' => '🔴 Delete',
                default => $action,
            })->all())
            ->placeholder('-');
    }

    public static function viewColumnValues(): TextEntry
    {
        return TextEntry::make('settings.values')
            ->label(__('resources/notificationSetting/strings.infolist.column_values'))
            ->badge()
            ->wrap()
            ->html()
            ->listWithLineBreaks()
            ->placeholder('-');
    }

    public static function viewColumns(): TextEntry
    {
        return TextEntry::make('settings.columns')
            ->label(__('resources/notificationSetting/strings.infolist.columns'))
            ->badge()
            ->wrap()
            ->html()
            ->listWithLineBreaks()
            ->state(fn($record) => array_keys(
                NotificationSetting::getColumnValuesForSelectedColumns(
                    $record->getColumns() ?? [], $record->getTables() ?? [])
            ))
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/notificationSetting/strings.infolist.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/notificationSetting/strings.infolist.created_by'))
            ->placeholder('-');
    }


    public static function viewIsActive(): IconEntry
    {
        return IconEntry::make('settings.is_active')
            ->label(__('resources/notificationSetting/strings.infolist.is_active'))
            ->boolean()
            ->trueIcon('heroicon-m-check-circle')
            ->falseIcon('heroicon-m-x-circle')
            ->trueColor('success')
            ->falseColor('danger');
    }

    public static function viewNotes(): TextEntry
    {
        return TextEntry::make('notes')
            ->label(__('resources/notificationSetting/strings.infolist.notes'))
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewNotificationChannel(): TextEntry
    {
        return TextEntry::make('notification_type')
            ->label(__('resources/notificationSetting/strings.infolist.notification_type'))
            ->getStateUsing(fn($record) => $record->notification_channel)
            ->badge()
            ->placeholder('-');
    }

    public static function viewTables(): TextEntry
    {
        return TextEntry::make('settings.tables')
            ->label(__('resources/notificationSetting/strings.infolist.tables'))
            ->badge()
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/notificationSetting/strings.infolist.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/notificationSetting/strings.infolist.updated_by'))
            ->placeholder('-');
    }

    public static function viewUsers(): TextEntry
    {
        return TextEntry::make('recipient.*.name')
            ->label(__('resources/notificationSetting/strings.infolist.users'))
            ->badge()
            ->badge()
            ->wrap()
            ->html()
            ->listWithLineBreaks()
            ->placeholder('-');
    }
}
