<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Traits;

use App\Filament\Resources\General\FilterComponents;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Model;

trait Filters
{
    public static function getBankFilter(): SelectFilter
    {
        return SelectFilter::make('bank_id')
            ->label(__('resources/bankProfile/strings.filters.bank'))
            ->relationship('bank', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->searchable()
            ->preload();
    }

    public static function getCompanyFilter(): SelectFilter
    {
        return SelectFilter::make('company_id')
            ->label(__('resources/bankProfile/strings.filters.company'))
            ->relationship('company', app()->getLocale() === 'fa' ? 'name' : 'english_name')
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
            __('resources/bankProfile/strings.filters.created_from'),
            __('resources/bankProfile/strings.filters.created_until'),
        );
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/bankProfile/strings.filters.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getPaymentDueDateFilter(): Filter
    {
        return FilterComponents::dateRangeFilter(
            'payment_due_date',
            'payment_due_date',
            'payment_due_from',
            'payment_due_until',
            __('resources/bankProfile/strings.filters.payment_due_from'),
            __('resources/bankProfile/strings.filters.payment_due_until'),
        );
    }

    public static function getRegisteredOrderFilter(): SelectFilter
    {
        return SelectFilter::make('registered_order_id')
            ->label(__('resources/bankProfile/strings.filters.registered_order'))
            ->relationship('registeredOrder', 'ro_number')
            ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->formatted_name_without_date)
            ->columnSpan(2)
            ->searchable()
            ->preload();
    }

    public static function getStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status_id')
            ->label(__('resources/bankProfile/strings.filters.status'))
            ->relationship('status', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->searchable()
            ->preload();
    }

    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }
}
