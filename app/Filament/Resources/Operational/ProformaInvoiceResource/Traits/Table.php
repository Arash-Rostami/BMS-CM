<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use App\Filament\Resources\Operational\ProformaInvoiceResource\Enums\Source;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

trait Table
{
    public static function showBuyerCompany(): TextColumn
    {
        return TextColumn::make('buyerCompany.name')
            ->label(__('resources/proformaInvoice/strings.table.buyer_company'))
            ->searchable(
                query: fn (Builder $query, string $search) => $query->whereHas('buyerCompany', fn ($q) => $q->searchCompany($search)))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true)
            ->formatStateUsing(fn ($record): ?string => $record->buyerCompany?->localized_name);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/proformaInvoice/strings.table.created_at'))
            ->dateTime()
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

    public static function showID(): TextColumn
    {
        return TextColumn::make('id')
            ->label(__('resources/proformaInvoice/strings.table.id'))
            ->sortable()
            ->searchable('id')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showInvoiceDate(): TextColumn
    {
        return TextColumn::make('invoice_date')
            ->label(__('resources/proformaInvoice/strings.table.invoice_date'))
            ->date()
            ->formatStateUsing(fn ($record) => app()->getLocale() === 'fa' ? toPersianDate($record->invoice_date) : toGregorianDate($record->invoice_date))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showInvoiceNo(): TextColumn
    {
        return TextColumn::make('invoice_no')
            ->label(__('resources/proformaInvoice/strings.table.invoice_no'))
            ->searchable()
            ->sortable()
            ->badge()
            ->copyable()
            ->tooltip(fn ($record) => app()->getLocale() === 'fa' ? toPersianDate($record->invoice_date) : toGregorianDate($record->invoice_date))
            ->toggleable();
    }

    public static function showSellerCompany(): TextColumn
    {
        return TextColumn::make('sellerCompany.name')
            ->label(__('resources/proformaInvoice/strings.table.seller_company'))
            ->searchable(
                query: fn (Builder $query, string $search) => $query->whereHas('sellerCompany', fn ($q) => $q->searchCompany($search))
            )
            ->sortable()
            ->toggleable()
            ->formatStateUsing(fn ($record): ?string => $record->sellerCompany?->localized_name);
    }

    public static function showTotalAmount(): TextColumn
    {
        return TextColumn::make('total_amount')
            ->label(__('resources/proformaInvoice/strings.table.total_amount'))
            ->sortable()
            ->numeric(decimalPlaces: 2)
            ->toggleable(isToggledHiddenByDefault: true)
            ->formatStateUsing(fn (?float $state) => $state === null ? '-' : number_format($state, 2));
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/proformaInvoice/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showRegisteredOrdersCount(): TextColumn
    {
        return TextColumn::make('registered_orders_count')
            ->label(__('resources/proformaInvoice/strings.table.registered_orders_count'))
            ->icon('heroicon-o-document-check')
            ->alignEnd()
            ->badge()
            ->color(fn (?int $state): string => $state === 0 ? 'gray' : 'success')
            ->toggleable()
            ->sortable();
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/proformaInvoice/strings.table.updater'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    protected static function showSource(): TextColumn
    {
        return TextColumn::make('source')
            ->label(__('resources/general/strings.relevant_module.table.related_to'))
            ->badge()
            ->getStateUsing(fn ($record) => Source::getAllFromRecord($record))
            ->formatStateUsing(fn (Source $state): ?string => $state->getLabel())
            ->tooltip(fn (Source $state): ?string => $state->getTooltip())
            ->iconPosition(IconPosition::Before)
            ->icon(fn (Source $state): ?string => $state->getIcon())
            ->color(fn (Source $state): string => $state->getColor())
            ->wrap();
    }
}
