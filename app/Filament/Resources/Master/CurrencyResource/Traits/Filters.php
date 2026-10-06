<?php

namespace App\Filament\Resources\Master\CurrencyResource\Traits;

use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;

trait Filters
{
    public static function getThrashedFilter()
    {
        return TrashedFilter::make();
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/currency/strings.filters.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getUpdaterFilter(): SelectFilter
    {
        return SelectFilter::make('updated_by_id')
            ->label(__('resources/currency/strings.filters.updater'))
            ->relationship('updater', 'name')
            ->searchable()
            ->preload();
    }

    public static function getActiveFilter(): TernaryFilter
    {
        return TernaryFilter::make('is_active')
            ->label(__('resources/currency/strings.filters.is_active'))
            ->trueLabel(__('resources/currency/strings.filters.only_active'))
            ->falseLabel(__('resources/currency/strings.filters.only_inactive'));
    }

    public static function getInUseFilter(): TernaryFilter
    {
        return TernaryFilter::make('in_use')
            ->label(__('resources/currency/strings.filters.in_use'))
            ->trueLabel(__('resources/currency/strings.filters.only_in_use'))
            ->falseLabel(__('resources/currency/strings.filters.only_unused'))
            ->queries(
                true: fn (Builder $query) => $query->where(function (Builder $query) {
                    foreach (static::usageRelations() as $relation) {
                        $query->orWhereHas($relation);
                    }
                }),
                false: fn (Builder $query) => $query->where(function (Builder $query) {
                    foreach (static::usageRelations() as $relation) {
                        $query->whereDoesntHave($relation);
                    }
                }),
            );
    }
}
