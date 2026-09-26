<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Traits;

use App\Filament\Resources\General\FilterComponents;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;

trait Filters
{
    public static function getCreationDateFilter(): Filter
    {
        return FilterComponents::dateRangeFilter(
            'created_at',
            'created_at',
            'created_from',
            'created_until',
            __('resources/purchaseRequest/strings.filters.created_from'),
            __('resources/purchaseRequest/strings.filters.created_until'),
        );
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/purchaseRequest/strings.filters.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getDepartmentFilter(): SelectFilter
    {
        return SelectFilter::make('department_id')
            ->label(__('resources/purchaseRequest/strings.filters.department'))
            ->relationship('department', 'name')
            ->searchable()
            ->preload();
    }

    public static function getRequesterFilter(): SelectFilter
    {
        return SelectFilter::make('requester_id')
            ->label(__('resources/purchaseRequest/strings.filters.requester'))
            ->relationship('requester', 'name')
            ->searchable()
            ->preload();
    }

    public static function getStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status_id')
            ->label(__('resources/purchaseRequest/strings.filters.status'))
            ->relationship('status', 'name')
            ->searchable()
            ->preload();
    }

    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }

    public static function getUpdaterFilter(): SelectFilter
    {
        return SelectFilter::make('updated_by_id')
            ->label(__('resources/purchaseRequest/strings.filters.updater'))
            ->relationship('updater', 'name')
            ->searchable()
            ->preload();
    }

    public static function getUrgencyFilter(): SelectFilter
    {
        return SelectFilter::make('urgency_level')
            ->label(__('resources/purchaseRequest/strings.filters.urgency_level'))
            ->options([
                'low' => __('resources/purchaseRequest/strings.general.urgency.low'),
                'medium' => __('resources/purchaseRequest/strings.general.urgency.medium'),
                'high' => __('resources/purchaseRequest/strings.general.urgency.high'),
                'critical' => __('resources/purchaseRequest/strings.general.urgency.critical'),
            ]);
    }
}
