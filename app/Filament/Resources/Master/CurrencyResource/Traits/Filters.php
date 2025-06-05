<?php

namespace App\Filament\Resources\Master\CurrencyResource\Traits;

use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;

trait Filters
{
    public static function getThrashedFilter()
    {
        return TrashedFilter::make();
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/currency/strings.table.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getUpdaterFilter(): SelectFilter
    {
        return SelectFilter::make('updated_by_id')
            ->label(__('resources/currency/strings.table.updater'))
            ->relationship('updater', 'name')
            ->searchable()
            ->preload();
    }
}
