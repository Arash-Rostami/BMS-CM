<?php

namespace App\Filament\Resources\Operational\PaymentResource\Traits;

use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
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
                    ->label(__('resources/payment/strings.filters.created_from'))
                    ->native(false)
                    ->jalali(),
                DatePicker::make('created_until')
                    ->label(__('resources/payment/strings.filters.created_until'))
                    ->native(false)
                    ->jalali(),
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
            ->label(__('resources/payment/strings.filters.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getCurrencyFilter(): SelectFilter
    {
        return SelectFilter::make('currency_id')
            ->label(__('resources/payment/strings.filters.currency'))
            ->relationship('currency', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->searchable()
            ->preload();
    }

    public static function getPayeeFilter(): SelectFilter
    {
        return SelectFilter::make('payee_id')
            ->label(__('resources/payment/strings.filters.payee'))
            ->relationship('payee', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->searchable()
            ->preload();
    }

    public static function getPayorFilter(): SelectFilter
    {
        return SelectFilter::make('payor_id')
            ->label(__('resources/payment/strings.filters.payor'))
            ->relationship('payor', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->searchable()
            ->preload();
    }

    public static function getStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status_id')
            ->label(__('resources/payment/strings.filters.status'))
            ->relationship('status', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->searchable()
            ->preload();
    }

    public static function getTargetableFilter(): SelectFilter
    {
        return SelectFilter::make('targetable_type')
            ->label(__('resources/payment/strings.form.targetable'))
            ->options([
                PurchaseOrder::class => __('resources/payment/strings.form.targetable_purchase_order'),
                RegisteredOrder::class => __('resources/payment/strings.form.targetable_registered_order'),
            ]);
    }

    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }
}
