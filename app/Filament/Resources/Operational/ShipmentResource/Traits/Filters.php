<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Traits;

use App\Models\Shipment;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;

trait Filters
{
    public static function getCarrierFilter(): SelectFilter
    {
        return SelectFilter::make('company_id')
            ->label(__('resources/shipment/strings.form.carrier'))
            ->relationship('carrier', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->searchable()
            ->preload();
    }

    public static function getContainerStatusFilter(): SelectFilter
    {
        return SelectFilter::make('container_status_id')
            ->label(__('resources/shipment/strings.form.container_status'))
            ->relationship(
                'containerStatus',
                app()->getLocale() === 'fa' ? 'name' : 'english_name',
                fn ($query) => $query?->where('english_type', Shipment::TYPE_CONTAINER_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getCreationDateFilter(): Filter
    {
        return Filter::make('created_at')
            ->schema([
                DatePicker::make('created_from')
                    ->label(__('resources/shipment/strings.filters.created_from'))
                    ->native(false)
                    ->adaptive(),
                DatePicker::make('created_until')
                    ->label(__('resources/shipment/strings.filters.created_until'))
                    ->native(false)
                    ->adaptive(),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when($data['created_from'], fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                    ->when($data['created_until'], fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date));
            });
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/shipment/strings.table.created_by'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getEtaFilter(): Filter
    {
        return Filter::make('eta')
            ->schema([
                DatePicker::make('eta_from')
                    ->label(__('resources/shipment/strings.filters.eta_from'))
                    ->native(false)
                    ->adaptive(),
                DatePicker::make('eta_until')
                    ->label(__('resources/shipment/strings.filters.eta_until'))
                    ->native(false)
                    ->adaptive(),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when($data['eta_from'], fn (Builder $query, $date) => $query->whereDate('eta', '>=', $date))
                    ->when($data['eta_until'], fn (Builder $query, $date) => $query->whereDate('eta', '<=', $date));
            });
    }

    public static function getStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status_id')
            ->label(__('resources/shipment/strings.form.status'))
            ->relationship(
                'status',
                app()->getLocale() === 'fa' ? 'name' : 'english_name',
                fn ($query) => $query?->where('english_type', Shipment::TYPE_SHIPMENT_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getTrackingStatusFilter(): SelectFilter
    {
        return SelectFilter::make('shipment_status_id')
            ->label(__('resources/shipment/strings.form.shipment_status'))
            ->relationship(
                'trackingStatus',
                app()->getLocale() === 'fa' ? 'name' : 'english_name',
                fn ($query) => $query?->where('english_type', Shipment::TYPE_TRACKING_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getOperationStatusFilter(): SelectFilter
    {
        return SelectFilter::make('operation_status_id')
            ->label(__('resources/shipment/strings.form.operation_status'))
            ->relationship(
                'operationStatus',
                app()->getLocale() === 'fa' ? 'name' : 'english_name',
                fn ($query) => $query->where('english_type', Shipment::TYPE_OPERATION_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getDocStatusFilter(): SelectFilter
    {
        return SelectFilter::make('doc_status_id')
            ->label(__('resources/shipment/strings.form.doc_status'))
            ->relationship(
                'docStatus',
                app()->getLocale() === 'fa' ? 'name' : 'english_name',
                fn ($query) => $query->where('english_type', Shipment::TYPE_DOC_STATUS)
            )
            ->searchable()
            ->preload();
    }

    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }
}
