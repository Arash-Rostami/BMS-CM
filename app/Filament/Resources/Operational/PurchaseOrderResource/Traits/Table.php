<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Traits;

use App\Filament\Resources\Operational\PurchaseOrderResource\Enums\Source;
use App\Filament\Resources\Operational\PurchaseOrderResource\Enums\Status;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

trait Table
{
    public static function showBuyer(): TextColumn
    {
        return TextColumn::make('buyerCompany.name')
            ->label(__('resources/purchaseOrder/strings.table.buyer'))
            ->sortable()
            ->searchable(
                query: fn(Builder $query, string $search) => $query->whereHas('buyerCompany', fn($q) => $q->searchCompany($search))
            )
            ->formatStateUsing(fn($record): ?string => $record->buyerCompany?->getLocalizedNameAttribute())
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/purchaseOrder/strings.table.created_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/purchaseOrder/strings.table.creator'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showID(): TextColumn
    {
        return TextColumn::make('id')
            ->label(__('resources/proformaInvoice/strings.table.id'))
            ->sortable()
            ->searchable(query: fn(Builder $query, string $search): Builder => $query->where('purchase_orders.id', 'like', "%{$search}%"))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showOrderDate(): TextColumn
    {
        return TextColumn::make('order_date')
            ->label(__('resources/purchaseOrder/strings.table.order_date'))
            ->date()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showPoNumber(): TextColumn
    {
        return TextColumn::make('po_number')
            ->label(__('resources/purchaseOrder/strings.table.po_number'))
            ->searchable()
            ->badge()
            ->copyable()
            ->sortable()
            ->tooltip(fn($record) => $record->order_date->format('Y-m-d'));
    }

    public static function showSeller(): TextColumn
    {
        return TextColumn::make('sellerCompany.name')
            ->label(__('resources/purchaseOrder/strings.table.seller'))
            ->sortable()
            ->searchable(
                query: fn(Builder $query, string $search) => $query->whereHas('sellerCompany', fn($q) => $q->searchCompany($search))
            )
            ->toggleable()
            ->formatStateUsing(fn($record): ?string => $record->sellerCompany?->getLocalizedNameAttribute());
    }

    public static function showStatus(): TextColumn
    {
        return TextColumn::make('status.name')
            ->label(__('resources/purchaseOrder/strings.table.status'))
            ->badge()
            ->sortable()
            ->searchable(
                query: fn(Builder $query, string $search) => $query->orWhereHas('status', fn($q) => $q->searchStatus($search))
            )
            ->formatStateUsing(fn($record): ?string => $record->status?->getLocalizedNameAttribute())
            ->iconPosition(IconPosition::Before)
            ->icon(fn($record): ?string => Status::tryFrom($record->status?->english_name)?->getIcon() ?? 'heroicon-o-question-mark-circle')
            ->color(fn($record): string => Status::tryFrom($record->status?->english_name)?->getColor() ?? 'gray');
    }

    public static function showTotalAmount(): TextColumn
    {
        return TextColumn::make('total_amount')
            ->label(__('resources/purchaseOrder/strings.form.total_amount'))
            ->formatStateUsing(fn($record): ?string => $record->total_amount)
            ->numeric(decimalPlaces: 2)
            ->placeholder('-')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/purchaseOrder/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/purchaseOrder/strings.table.updater'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    protected static function showSource(): TextColumn
    {
        return TextColumn::make('source')
            ->label(__('resources/general/strings.relevant_module.table.related_to'))
            ->badge()
            ->getStateUsing(fn($record) => Source::getAllFromRecord($record))
            ->formatStateUsing(fn(Source $state): ?string => $state->getLabel())
            ->tooltip(fn(Source $state): ?string => $state->getTooltip())
            ->iconPosition(IconPosition::Before)
            ->icon(fn(Source $state): ?string => $state->getIcon())
            ->color(fn(Source $state): string => $state->getColor())
            ->wrap();
    }
}
