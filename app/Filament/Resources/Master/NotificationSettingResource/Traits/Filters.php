<?php

namespace App\Filament\Resources\Master\NotificationSettingResource\Traits;

use App\Models\NotificationSetting;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

trait Filters
{
    public static function getActionsFilter(): SelectFilter
    {
        return SelectFilter::make('actions')
            ->label(__('resources/notificationSetting/strings.filters.actions'))
            ->options([
                'create' => '🟢 Create',
                'update' => '🟡 Update',
                'delete' => '🔴 Delete',
            ])
            ->query(function ($query, array $data) {
                if (filled($data['values'])) {
                    $query->where(function ($q) use ($data) {
                        foreach ($data['values'] as $action) {
                            $q->orWhereJsonContains('settings->actions', $action);
                        }
                    });
                }
            })
            ->searchable()
            ->multiple();
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/notificationSetting/strings.filters.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getIsActiveFilter(): TernaryFilter
    {
        return TernaryFilter::make('settings->is_active')
            ->label(__('resources/notificationSetting/strings.filters.is_active'))
            ->trueLabel(__('resources/notificationSetting/strings.filters.active'))
            ->falseLabel(__('resources/notificationSetting/strings.filters.inactive'))
            ->nullable();
    }

    public static function getNotificationChannelFilter(): SelectFilter
    {
        return SelectFilter::make('notification_type')
            ->label(__('resources/notificationSetting/strings.filters.notification_type'))
            ->options(NotificationSetting::notificationChannel());
    }

    public static function getTablesFilter(): SelectFilter
    {
        return SelectFilter::make('tables')
            ->label(__('resources/notificationSetting/strings.filters.tables'))
            ->options(fn() => NotificationSetting::query()
                ->whereNotNull('settings')
                ->get()
                ->pluck('settings')
                ->filter()
                ->pluck('tables')
                ->flatten()
                ->unique()
                ->sort()
                ->mapWithKeys(fn($table) => [$table => $table])
            )
            ->query(function ($query, array $data) {
                if (filled($data['values'])) {
                    $query->where(function ($q) use ($data) {
                        foreach ($data['values'] as $table) {
                            $q->orWhereJsonContains('settings->tables', $table);
                        }
                    });
                }
            })
            ->searchable()
            ->multiple();
    }

    public static function getUpdaterFilter(): SelectFilter
    {
        return SelectFilter::make('updated_by_id')
            ->label(__('resources/notificationSetting/strings.filters.updater'))
            ->relationship('updater', 'name')
            ->searchable()
            ->preload();
    }
}
