<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

trait Table
{
    public static function showID(): TextColumn
    {
        return TextColumn::make('id')
            ->label(__('resources/proformaInvoice/strings.table.id'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showPurchaseRequests(): TextColumn
    {
        return TextColumn::make('purchaseRequests')
            ->label(__('resources/proformaInvoice/strings.form.purchase_requests'))
            ->html()
            ->default('-')
            ->getStateUsing(fn($record) => $record->purchaseRequests->pluck('formatted_name_without_date')->implode('<br>'))
            ->searchable(
                query: fn(Builder $query, string $search) => $query->whereHas(
                    'purchaseRequests',
                    fn(Builder $q) => $q->searchAll($search)
                ), isIndividual: true)
            ->toggleable();
    }


    public static function showInvoiceNo(): TextColumn
    {
        return TextColumn::make('invoice_no')
            ->label(__('resources/proformaInvoice/strings.table.invoice_no'))
            ->searchable()
            ->sortable()
            ->tooltip(fn($record) => app()->getLocale() === 'fa' ? toPersianDate($record->invoice_date) : toGregorianDate($record->invoice_date))
            ->toggleable();
    }

    public static function showSellerCompany(): TextColumn
    {
        return TextColumn::make("sellerCompany.name")
            ->label(__('resources/proformaInvoice/strings.table.seller_company'))
            ->searchable()
            ->sortable()
            ->toggleable()
            ->formatStateUsing(fn($record): ?string => $record->sellerCompany?->localized_name);
    }

    public static function showConsigneeCompany(): TextColumn
    {
        return TextColumn::make("consigneeCompany.name")
            ->label(__('resources/proformaInvoice/strings.table.consignee_company'))
            ->searchable()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true)
            ->formatStateUsing(fn($record): ?string => $record->consigneeCompany?->localized_name);
    }

    public static function showTotalAmount(): TextColumn
    {
        return TextColumn::make('total_cfr_amount')
            ->label(__('resources/proformaInvoice/strings.table.total_cfr_amount'))
            ->sortable()
            ->numeric(decimalPlaces: 2)
            ->toggleable()
            ->formatStateUsing(fn(?float $state) => $state === null ? '-' : number_format($state, 2));
    }

    public static function showInvoiceDate(): TextColumn
    {
        return TextColumn::make('invoice_date')
            ->label(__('resources/proformaInvoice/strings.table.invoice_date'))
            ->date()
            ->formatStateUsing(fn($record) => app()->getLocale() === 'fa' ? toPersianDate($record->invoice_date) : toGregorianDate($record->invoice_date))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/proformaInvoice/strings.table.creator'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/proformaInvoice/strings.table.created_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/proformaInvoice/strings.table.updater'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/proformaInvoice/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
