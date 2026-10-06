<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Traits;

use App\Filament\Resources\General\FilterComponents;
use App\Models\Shipment;
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
        return FilterComponents::dateRangeFilter(
            'created_at',
            'created_at',
            'created_from',
            'created_until',
            __('resources/shipment/strings.filters.created_from'),
            __('resources/shipment/strings.filters.created_until'),
        );
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
        return FilterComponents::dateRangeFilter(
            'eta',
            'eta',
            'eta_from',
            'eta_until',
            __('resources/shipment/strings.filters.eta_from'),
            __('resources/shipment/strings.filters.eta_until'),
        );
    }

    public static function getOverdueFilter(): Filter
    {
        return Filter::make('overdue')
            ->label(__('resources/shipment/strings.filters.overdue'))
            ->query(fn (Builder $query): Builder => $query->whereNull('exit_date')->where('eta', '<', today()));
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
