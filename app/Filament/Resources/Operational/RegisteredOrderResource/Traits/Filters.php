<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Traits;

use App\Filament\Resources\General\FilterComponents;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;

trait Filters
{
    public static function getBuyerFilter(): SelectFilter
    {
        return SelectFilter::make('buyer_id')
            ->label(__('resources/registeredOrder/strings.filters.buyer'))
            ->relationship('buyerCompany', 'name')
            ->searchable()
            ->preload();
    }

    public static function getCreationDateFilter(): Filter
    {
        return FilterComponents::dateRangeFilter(
            'created_at',
            'created_at',
            'created_from',
            'created_until',
            __('resources/registeredOrder/strings.filters.created_from'),
            __('resources/registeredOrder/strings.filters.created_until'),
        );
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/registeredOrder/strings.filters.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getIncotermsFilter(): SelectFilter
    {
        return SelectFilter::make('incoterms')
            ->label(__('resources/registeredOrder/strings.filters.incoterms'))
            ->options(fn () => __('resources/registeredOrder/strings.general.delivery_terms'));
    }

    public static function getStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status_id')
            ->label(__('resources/registeredOrder/strings.filters.status'))
            ->relationship('status', 'name')
            ->searchable()
            ->preload();
    }

    public static function getSellerFilter(): SelectFilter
    {
        return SelectFilter::make('seller_id')
            ->label(__('resources/registeredOrder/strings.filters.seller'))
            ->relationship('sellerCompany', 'name')
            ->searchable()
            ->preload();
    }

    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }
}
