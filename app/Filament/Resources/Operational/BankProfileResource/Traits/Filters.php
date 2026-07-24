<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Traits;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
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
        return Filter::make('created_at')
            ->schema([
                DatePicker::make('created_from')
                    ->label(__('resources/bankProfile/strings.filters.created_from'))
                    ->native(false)
                    ->adaptive(),
                DatePicker::make('created_until')
                    ->label(__('resources/bankProfile/strings.filters.created_until'))
                    ->native(false)
                    ->adaptive(),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when($data['created_from'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                    ->when($data['created_until'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
            });
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
        return Filter::make('payment_due_date')
            ->schema([
                DatePicker::make('payment_due_from')
                    ->label(__('resources/bankProfile/strings.filters.payment_due_from'))
                    ->native(false)
                    ->adaptive(),
                DatePicker::make('payment_due_until')
                    ->label(__('resources/bankProfile/strings.filters.payment_due_until'))
                    ->native(false)
                    ->adaptive(),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when($data['payment_due_from'], fn (Builder $query, $date): Builder => $query->whereDate('payment_due_date', '>=', $date))
                    ->when($data['payment_due_until'], fn (Builder $query, $date): Builder => $query->whereDate('payment_due_date', '<=', $date));
            });
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
