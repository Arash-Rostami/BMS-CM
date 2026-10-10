<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Traits;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\Visibility;
use App\Models\CalendarRule;
use App\Services\Calendar\CalendarModules;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;

trait Filters
{
    public static function getSubjectFilter(): SelectFilter
    {
        return SelectFilter::make('subject')
            ->label(__('resources/calendarRule/strings.filters.subject'))
            ->options(fn (): array => collect(CalendarModules::all())
                ->map(fn (array $meta): string => $meta['label'])
                ->all());
    }

    public static function getTypeFilter(): SelectFilter
    {
        return SelectFilter::make('type')
            ->label(__('resources/calendarRule/strings.filters.type'))
            ->options(RuleType::class);
    }

    public static function getVisibilityFilter(): SelectFilter
    {
        return SelectFilter::make('visibility')
            ->label(__('resources/calendarRule/strings.filters.visibility'))
            ->options(Visibility::class);
    }

    public static function getNotificationChannelFilter(): SelectFilter
    {
        return SelectFilter::make('notification_type')
            ->label(__('resources/calendarRule/strings.filters.notification_type'))
            ->options(CalendarRule::notificationChannel());
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/calendarRule/strings.filters.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getUpdaterFilter(): SelectFilter
    {
        return SelectFilter::make('updated_by_id')
            ->label(__('resources/calendarRule/strings.filters.updater'))
            ->relationship('updater', 'name')
            ->searchable()
            ->preload();
    }

    public static function getIsActiveFilter(): TernaryFilter
    {
        return TernaryFilter::make('is_active')
            ->label(__('resources/calendarRule/strings.filters.is_active'))
            ->trueLabel(__('resources/calendarRule/strings.filters.only_active'))
            ->falseLabel(__('resources/calendarRule/strings.filters.only_inactive'));
    }

    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }
}
