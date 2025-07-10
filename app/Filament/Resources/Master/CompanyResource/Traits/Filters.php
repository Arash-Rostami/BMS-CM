<?php

namespace App\Filament\Resources\Master\CompanyResource\Traits;


use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
            ->label(__('resources/company/strings.table.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getUpdaterFilter(): SelectFilter
    {
        return SelectFilter::make('updated_by_id')
            ->label(__('resources/company/strings.table.updater'))
            ->relationship('updater', 'name')
            ->searchable()
            ->preload();
    }

    public static function getActiveFilter(): TernaryFilter
    {
        return TernaryFilter::make('is_active')
            ->label(__('resources/company/strings.table.is_active'))
            ->trueLabel(__('resources/company/strings.table.only_active'))
            ->falseLabel(__('resources/company/strings.table.only_inactive'));
    }
}
