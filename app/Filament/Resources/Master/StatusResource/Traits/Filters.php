<?php

namespace App\Filament\Resources\Master\StatusResource\Traits;

use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\SelectFilter;

trait Filters
{
    public static function getThrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('created_by_id')
            ->label(__('resources/status/strings.table.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getUpdaterFilter(): SelectFilter
    {
        return SelectFilter::make('updated_by_id')
            ->label(__('resources/status/strings.table.updater'))
            ->relationship('updater', 'name')
            ->searchable()
            ->preload();
    }
}
