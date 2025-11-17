<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Traits;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;

trait Filters
{
    public static function getBuyerFilter(): SelectFilter
    {
        return SelectFilter::make('buyer_id')
            ->label(__('resources/purchaseOrder/strings.filters.buyer'))
            ->relationship('buyerCompany', 'name')
            ->searchable()
            ->preload();
    }

    public static function getCreationDateFilter(): Filter
    {
        return Filter::make('created_at')
            ->schema([
                DatePicker::make('created_from')
                    ->label(__('resources/purchaseOrder/strings.filters.created_from'))
                    ->native(false),
                DatePicker::make('created_until')
                    ->label(__('resources/purchaseOrder/strings.filters.created_until'))
                    ->native(false),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when($data['created_from'], fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                    ->when($data['created_until'], fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
            });
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/purchaseOrder/strings.filters.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getCurrencyFilter(): SelectFilter
    {
        return SelectFilter::make('currency_id')
            ->label(__('resources/purchaseOrder/strings.filters.currency'))
            ->relationship(
                name: 'currency',
                titleAttribute: app()->getLocale() === 'fa' ? 'name' : 'english_name',
            )
            ->searchable()
            ->preload();
    }

    public static function getIncotermsFilter(): SelectFilter
    {
        return SelectFilter::make('incoterms')
            ->label(__('resources/purchaseOrder/strings.filters.incoterms'))
            ->options(fn() => __('resources/purchaseOrder/strings.general.delivery_terms'));
    }

    public static function getStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status_id')
            ->label(__('resources/purchaseOrder/strings.filters.status'))
            ->relationship('status', 'name')
            ->searchable()
            ->preload();
    }

    public static function getSellerFilter(): SelectFilter
    {
        return SelectFilter::make('seller_id')
            ->label(__('resources/purchaseOrder/strings.filters.seller'))
            ->relationship('sellerCompany', 'name')
            ->searchable()
            ->preload();
    }

    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }
}
