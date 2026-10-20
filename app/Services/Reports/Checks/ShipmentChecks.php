<?php

namespace App\Services\Reports\Checks;

use App\Filament\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\Reports\Support\ReportDates;
use App\Services\Reports\Support\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

final class ShipmentChecks
{
    /**
     * @return array<int, Check>
     */
    public static function all(): array
    {
        return [self::missingTrackingStatus(), self::customsQuantityAboveShipped()];
    }

    private static function missingTrackingStatus(): Check
    {
        return new Check(
            key: 'shipment.missing_tracking_status',
            model: Shipment::class,
            severity: 'warning',
            reports: ['operations'],
            query: fn (Builder $base, ReportFilters $filters): Builder => $base
                ->whereIn('shipments.registered_order_id', RegisteredOrderChecks::openOrders($filters)->select('registered_orders.id'))
                ->whereNull('shipments.shipment_status_id')
                ->where(fn (Builder $dated) => $dated->whereNotNull('shipments.exit_date')->orWhereNotNull('shipments.etd')),
            offending: fn () => '-',
            expected: fn (): string => __('resources/report/strings.attention.checks.shipment.missing_tracking_status.expected'),
            fix: ['resource' => ShipmentResource::class, 'tab' => 'resources/shipment/strings.form.tab_logistics', 'field' => 'shipment_status_id'],
        );
    }

    private static function customsQuantityAboveShipped(): Check
    {
        return new Check(
            key: 'shipment.customs_quantity_above_shipped',
            model: Shipment::class,
            severity: 'warning',
            reports: ['customs'],
            query: fn (Builder $base, ReportFilters $filters): Builder => self::customsScoped($base, $filters)
                ->whereHas('customs')
                ->whereNotNull('shipments.customs_quantity')
                ->whereNotNull('shipments.shipped_quantity')
                ->whereColumn('shipments.customs_quantity', '>', 'shipments.shipped_quantity'),
            offending: fn (Shipment $shipment): string => preciseNumber($shipment->customs_quantity).' > '.preciseNumber($shipment->shipped_quantity),
            expected: fn (): string => __('resources/report/strings.attention.checks.shipment.customs_quantity_above_shipped.expected'),
            fix: ['resource' => ShipmentResource::class, 'tab' => 'resources/shipment/strings.form.tab_logistics', 'field' => 'customs_quantity'],
        );
    }

    private static function customsScoped(Builder $query, ReportFilters $filters): Builder
    {
        return $query
            ->when($filters->registeredOrderId, fn (Builder $shipments, int $id): Builder => $shipments->where('shipments.registered_order_id', $id))
            ->when($filters->sellerId, fn (Builder $shipments, int $id): Builder => $shipments->whereHas('registeredOrder', fn (Builder $order): Builder => $order->where('registered_orders.seller_id', $id)))
            ->when($filters->buyerId, fn (Builder $shipments, int $id): Builder => $shipments->whereHas('registeredOrder', fn (Builder $order): Builder => $order->where('registered_orders.buyer_id', $id)))
            ->tap(fn (Builder $shipments): Builder => InTransitChecks::productScoped($shipments, $filters))
            ->when($filters->docStatusId, fn (Builder $shipments, int $id): Builder => $shipments->where('shipments.doc_status_id', $id))
            ->when($filters->companyId, fn (Builder $shipments, int $id): Builder => $shipments->where('shipments.company_id', $id))
            ->when($filters->dateFrom || $filters->dateUntil, fn (Builder $shipments): Builder => $shipments->whereHas('customs', fn (Builder $customs): Builder => ReportDates::apply($customs, $filters, CustomChecks::dateBases(), 'doc_submission_date')));
    }
}
