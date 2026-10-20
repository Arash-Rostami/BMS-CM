<?php

namespace App\Filament\Resources\Master\ActivityResource\Traits;

use App\Filament\Resources\General\FilterComponents;
use App\Models\User;
use App\Services\AuditChanges;
use App\Services\PermissionLabeler;
use App\Services\SmartCacheManager;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

trait Filters
{
    public static function getSubjectTypeFilter(): SelectFilter
    {
        return SelectFilter::make('module')
            ->attribute('subject_type')
            ->label(__('resources/activity/strings.filters.subject_type'))
            ->options(fn (): array => collect(app(AuditChanges::class)->viewableModels())
                ->mapWithKeys(fn (string $class): array => [$class => PermissionLabeler::getEntityLabel($class)])
                ->all());
    }

    public static function getEventFilter(): SelectFilter
    {
        return SelectFilter::make('event')
            ->label(__('resources/activity/strings.filters.event'))
            ->options(fn (): array => static::eventLabels());
    }

    public static function getCauserFilter(): SelectFilter
    {
        return SelectFilter::make('causer_id')
            ->label(__('resources/activity/strings.filters.causer'))
            ->options(fn (): array => SmartCacheManager::remember('Activity', ['filter' => 'causer'], 5, fn () => User::withTrashed()->pluck('name', 'id')->toArray()))
            ->searchable();
    }

    public static function getCreationDateFilter(): Filter
    {
        return FilterComponents::dateRangeFilter(
            'created_at',
            'created_at',
            'created_from',
            'created_until',
            __('resources/activity/strings.filters.created_from'),
            __('resources/activity/strings.filters.created_until'),
        );
    }

    public static function eventLabels(): array
    {
        return collect(['created', 'updated', 'deleted', 'restored'])
            ->mapWithKeys(fn (string $event): array => [$event => __('resources/general/strings.audit.events.'.$event)])
            ->all();
    }
}
