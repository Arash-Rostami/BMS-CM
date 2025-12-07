<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Traits;

use App\Models\Shipment;
use App\Models\Status;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait Table
{
    public static function showBlNumber(): TextColumn
    {
        return TextColumn::make('bl_number')
            ->label(__('resources/shipment/strings.table.bl_number'))
            ->searchable()
            ->copyable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCarrier(): TextColumn
    {
        return TextColumn::make('carrier.name')
            ->label(__('resources/shipment/strings.table.carrier'))
            ->sortable()
            ->searchable(query: fn(Builder $query, string $search) => $query->whereHas('carrier', fn($q) => $q->searchCompany($search)))
            ->formatStateUsing(fn(Shipment $record): ?string => $record->carrier?->getLocalizedNameAttribute())
            ->toggleable(isToggledHiddenByDefault: true);
    }


    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/shipment/strings.table.created_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/shipment/strings.table.created_by'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showId(): TextColumn
    {
        return TextColumn::make('id')
            ->label(__('resources/shipment/strings.table.id'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showPart(): TextColumn
    {
        return TextColumn::make('part')
            ->label(__('resources/shipment/strings.table.part'))
            ->sortable()
            ->badge()
            ->color('info')
            ->toggleable();
    }

    public static function showRegisteredOrder(): TextColumn
    {
        return TextColumn::make('registeredOrder.ro_number')
            ->label(__('resources/shipment/strings.table.registered_order'))
            ->searchable(
                query: fn(Builder $query, string $search) => $query->whereHas(
                    'registeredOrder',
                    fn(Builder $q) => $q->searchAll($search)
                ), isIndividual: true)
            ->sortable()
            ->copyable()
            ->iconPosition(IconPosition::Before)
            ->icon('heroicon-o-document-check')
            ->badge()
            ->color('info')
            ->tooltip(fn(Shipment $record) => ' 💼 ' .$record->contract_no)
            ->toggleable();
    }

    public static function showShipmentNo(): TextColumn
    {
        return TextColumn::make('shipment_no')
            ->label(__('resources/shipment/strings.table.shipment_no'))
            ->searchable()
            ->badge()
            ->copyable()
            ->sortable()
            ->tooltip(fn(Shipment $record) => $record->bl_number);
    }

    public static function showStatus(): TextColumn
    {
        return TextColumn::make('status.name')
            ->label(__('resources/shipment/strings.table.status'))
            ->badge()
            ->sortable()
            ->searchable(query: fn(Builder $query, string $search) => $query->orWhereHas('status', fn($q) => $q->searchStatus($search)))
            ->formatStateUsing(fn($record) => $record->status?->getLocalizedNameAttribute())
            ->color('primary');
    }

    public static function showTrackingStatus(): TextColumn
    {
        return TextColumn::make('trackingStatus.name')
            ->label(__('resources/shipment/strings.table.tracking_status'))
            ->badge()
            ->formatStateUsing(fn($record) => $record->trackingStatus?->getLocalizedNameAttribute())
            ->color('warning')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/shipment/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/shipment/strings.table.updated_by'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
