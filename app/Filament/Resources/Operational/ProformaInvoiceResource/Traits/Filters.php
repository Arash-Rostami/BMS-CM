<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;

trait Filters
{


    public static function getConsigneeCompanyFilter(): SelectFilter
    {
        return SelectFilter::make('consignee_company_id')
            ->label(__('resources/proformaInvoice/strings.filters.consignee_company'))
            ->relationship('consigneeCompany', 'name')
            ->searchable()
            ->preload();
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/proformaInvoice/strings.filters.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getDeliveryTermsFilter(): SelectFilter
    {
        return SelectFilter::make('delivery_terms')
            ->label(__('resources/proformaInvoice/strings.filters.delivery_terms'))
            ->options(__('resources/proformaInvoice/strings.general.delivery_terms'));
    }

    public static function getHasPurchaseOrdersFilter(): Filter
    {
        return Filter::make('has_purchase_orders')
            ->label(__('resources/proformaInvoice/strings.filters.has_purchase_orders'))
            ->query(fn(Builder $query): Builder => $query->has('purchaseOrders'))
            ->toggle();
    }

    public static function getHasPurchaseRequestsFilter(): Filter
    {
        return Filter::make('has_purchase_requests')
            ->label(__('resources/proformaInvoice/strings.filters.has_purchase_requests'))
            ->query(fn(Builder $query): Builder => $query->has('purchaseRequests'))
            ->toggle();
    }

    public static function getInvoiceDateFilter(): Filter
    {
        return Filter::make('invoice_date')
            ->schema([
                DatePicker::make('invoice_date_from')
                    ->label(__('resources/proformaInvoice/strings.filters.invoice_date_from'))
                    ->when(app()->isLocale('fa'), fn($column) => $column->jalali())
                    ->native(false),
                DatePicker::make('invoice_date_until')
                    ->label(__('resources/proformaInvoice/strings.filters.invoice_date_until'))
                    ->when(app()->isLocale('fa'), fn($column) => $column->jalali())
                    ->native(false),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when(
                        $data['invoice_date_from'],
                        fn(Builder $query, $date): Builder => $query->whereDate('invoice_date', '>=', $date),
                    )
                    ->when(
                        $data['invoice_date_until'],
                        fn(Builder $query, $date): Builder => $query->whereDate('invoice_date', '<=', $date),
                    );
            });
    }

    public static function getMainCurrencyFilter(): SelectFilter
    {
        return SelectFilter::make('main_currency_id')
            ->label(__('resources/proformaInvoice/strings.filters.main_currency'))
            ->relationship('mainCurrency', 'name');
    }

    public static function getSellerCompanyFilter(): SelectFilter
    {
        return SelectFilter::make('seller_company_id')
            ->label(__('resources/proformaInvoice/strings.filters.seller_company'))
            ->relationship('sellerCompany', 'name')
            ->searchable()
            ->preload();
    }

    public static function getTransportModeFilter(): SelectFilter
    {
        return SelectFilter::make('transport_mode')
            ->label(__('resources/proformaInvoice/strings.filters.transport_mode'))
            ->options(__('resources/proformaInvoice/strings.general.transport_modes'));
    }

    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }

    public static function getUpdaterFilter(): SelectFilter
    {
        return SelectFilter::make('updated_by_id')
            ->label(__('resources/proformaInvoice/strings.table.updater'))
            ->relationship('updater', 'name')
            ->searchable()
            ->preload();
    }
}
