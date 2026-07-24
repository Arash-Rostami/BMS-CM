<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Traits;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;

trait Filters
{
    public static function getCreationDateFilter(): Filter
    {
        return Filter::make('created_at')
            ->schema([
                DatePicker::make('created_from')
                    ->label(__('resources/purchaseRequest/strings.filters.created_from'))
                    ->adaptive()
                    ->native(false),
                DatePicker::make('created_until')
                    ->label(__('resources/purchaseRequest/strings.filters.created_until'))
                    ->adaptive()
                    ->native(false),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when(
                        $data['created_from'],
                        fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                    )
                    ->when(
                        $data['created_until'],
                        fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                    );
            });
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
