<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Traits;

use App\Filament\Resources\Operational\RegisteredOrderResource\Enums\Source;
use App\Filament\Resources\Operational\RegisteredOrderResource\Enums\Status;
use App\Models\Company;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;


trait Table
{
    public static function showBuyer(): TextColumn
    {
        return TextColumn::make('buyerCompany.name')
            ->label(__('resources/registeredOrder/strings.table.buyer'))
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
            ->label(__('resources/registeredOrder/strings.table.created_at'))->dateTime()
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/registeredOrder/strings.table.created_by'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showId(): TextColumn
    {
        return TextColumn::make('id')
            ->label(__('resources/registeredOrder/strings.table.id'))
            ->sortable()
            ->searchable(query: fn(Builder $query, string $search): Builder => $query->where('registered_orders.id', 'like', "%{$search}%"))
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showOrderDate(): TextColumn
    {
        return TextColumn::make('order_date')
            ->label(__('resources/registeredOrder/strings.table.order_date'))
            ->date()
            ->formatStateUsing(fn($record) => app()->getLocale() === 'fa' ? toPersianDate($record->order_date) : toGregorianDate($record->order_date))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showPurchaseOrdersCount(): TextColumn
    {
        return TextColumn::make('purchase_orders_count')
            ->label(__('resources/registeredOrder/strings.table.purchase_orders_count'))
            ->icon('heroicon-o-shopping-bag')
            ->alignEnd()
            ->badge()
            ->color(fn(?int $state): string => $state === 0 ? 'gray' : 'success')
            ->toggleable()
            ->sortable();
    }

    public static function showRoNumber(): TextColumn
    {
        return TextColumn::make('ro_number')
            ->label(__('resources/registeredOrder/strings.table.ro_number'))
            ->searchable()
            ->badge()
            ->copyable()
            ->sortable()
            ->tooltip(fn($record) => $record->order_date->format('Y-m-d'));
    }

    public static function showOfficialRegistrationNo(): TextColumn
    {
        return TextColumn::make('official_registration_no')
            ->label(__('resources/registeredOrder/strings.form.official_registration_no'))
            ->searchable()
            ->badge()
            ->copyable()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCtNumber(): TextColumn
    {
        return TextColumn::make('contract_no')
            ->label(__('resources/registeredOrder/strings.form.contract_number'))
            ->searchable()
            ->badge()
            ->copyable()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showSeller(): TextColumn
    {
        return TextColumn::make('sellerCompany.name')
            ->label(__('resources/registeredOrder/strings.table.seller'))
            ->sortable(query: Company::getSellerCompanySort('registered_orders.seller_id'))
            ->searchable(
                query: fn(Builder $query, string $search) => $query->whereHas('sellerCompany', fn($q) => $q->searchCompany($search))
            )
            ->toggleable()
            ->formatStateUsing(fn($record): ?string => $record->sellerCompany?->getLocalizedNameAttribute());
    }

    public static function showStatus(): TextColumn
    {
        return TextColumn::make('status.name')
            ->label(__('resources/registeredOrder/strings.table.status'))
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

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/registeredOrder/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/registeredOrder/strings.table.updated_by'))
            ->sortable()
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
