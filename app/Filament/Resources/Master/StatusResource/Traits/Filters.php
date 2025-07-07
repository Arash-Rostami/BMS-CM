<?php

namespace App\Filament\Resources\Master\StatusResource\Traits;

use App\Models\Status;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;

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

    public static function getTypeFilter(): SelectFilter
    {
        $column = app()->getLocale() === 'fa' ? 'type' : 'english_type';

        return SelectFilter::make($column)
            ->label(__('resources/status/strings.table.' . $column))
            ->multiple()
            ->searchable()
            ->options(fn(): array => Status::query()
                ->distinct($column)
                ->orderBy($column)
                ->pluck($column, $column)
                ->toArray()
            );
    }
}
