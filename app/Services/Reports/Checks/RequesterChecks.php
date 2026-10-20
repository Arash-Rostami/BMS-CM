<?php

namespace App\Services\Reports\Checks;

use App\Filament\Resources\Operational\PurchaseRequestResource\Enums\Status as PurchaseRequestStatus;
use App\Filament\Resources\PurchaseRequestResource;
use App\Filament\Resources\ShipmentResource;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Shipment;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Rules\RequesterStages;
use App\Services\Reports\Support\ReportDates;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Support\SqlToday;
use Closure;
use Illuminate\Database\Eloquent\Builder;

final class RequesterChecks
{
    private const TAB = 'resources/purchaseRequest/strings.form.tab_general';

    private const LANG = 'resources/report/strings.attention.checks.';

    /**
     * @return array<int, Check>
     */
    public static function all(): array
    {
        return [
            self::missingCostCenter(),
            self::requiredByPassed(),
            self::itemWithoutProduct(),
            self::chainUnspecific(),
            self::sharedShipment(),
            self::unitMismatch(),
            self::missingRequiredByDate(),
            self::shippedAboveRequested(),
            self::noOrderAfterDays(),
        ];
    }

    public static function narrowRequests(Builder $query, ReportFilters $filters, string $table): Builder
    {
        $query
            ->when($filters->costCenterId, fn (Builder $requests, int $id): Builder => $requests->where("{$table}.cost_center_id", $id))
            ->when($filters->requesterId, fn (Builder $requests, int $id): Builder => $requests->where("{$table}.requester_id", $id))
            ->when($filters->statusIds, fn (Builder $requests, array $ids): Builder => $requests->whereIn("{$table}.status_id", $ids));

        return ReportDates::apply($query, $filters, ['required_by_date' => "{$table}.required_by_date", 'created_at' => "{$table}.created_at"], 'required_by_date');
    }

    private static function scoped(Builder $query, ReportFilters $filters): Builder
    {
        return self::narrowRequests($query->whereHas('items'), $filters, 'purchase_requests')
            ->when($filters->registeredOrderId, fn (Builder $requests, int $id): Builder => $requests->whereHas('registeredOrders', fn (Builder $orders): Builder => $orders->where('registered_orders.id', $id)))
            ->when($filters->productId, fn (Builder $requests, int $id): Builder => $requests->whereHas('items', fn (Builder $items): Builder => $items->where('purchase_request_items.product_id', $id)))
            ->when($filters->categoryId, fn (Builder $requests): Builder => $requests->whereHas('items', fn (Builder $items): Builder => $items->whereIn('purchase_request_items.product_id', Product::query()->whereIn('category_id', $filters->categoryIds())->select('id'))));
    }

    private static function missingCostCenter(): Check
    {
        return self::request('missing_cost_center', 'warning', 'cost_center_id',
            fn (Builder $query, ReportFilters $filters): Builder => self::scoped($query, $filters)->whereNull('purchase_requests.cost_center_id'),
            fn (): string => '-');
    }

    private static function requiredByPassed(): Check
    {
        return self::request('required_by_passed', 'warning', 'required_by_date',
            fn (Builder $query, ReportFilters $filters): Builder => self::scoped($query, $filters)
                ->whereRaw('purchase_requests.required_by_date < '.RequesterStages::lateBefore($filters))
                ->whereRaw('exists (select 1 from purchase_request_items where purchase_request_items.purchase_request_id = purchase_requests.id and purchase_request_items.deleted_at is null and ('.RequesterStages::stageSql($filters).") <> 'cleared')"),
            fn (PurchaseRequest $request): string => adaptiveDate($request->required_by_date));
    }

    private static function itemWithoutProduct(): Check
    {
        return new Check(
            key: 'shipment.item_without_product',
            model: Shipment::class,
            severity: 'warning',
            reports: ['requester'],
            query: fn (Builder $base, ReportFilters $filters): Builder => $base
                ->whereRaw(InTransitChecks::voidSqlFor($filters))
                ->when($filters->registeredOrderId, fn (Builder $shipments, int $id): Builder => $shipments->where('shipments.registered_order_id', $id))
                ->whereRaw('exists (select 1 from registered_order_purchase_request l join purchase_requests p on p.id = l.purchase_request_id and p.deleted_at is null join registered_orders o on o.id = l.registered_order_id and o.deleted_at is null where l.registered_order_id = shipments.registered_order_id)')
                ->whereRaw('exists (select 1 from shipment_items w where w.shipment_id = shipments.id and w.product_id is null)'),
            offending: fn (): string => '-',
            expected: fn (): string => __(self::LANG.'shipment.item_without_product.expected'),
            fix: ['resource' => ShipmentResource::class, 'tab' => 'resources/shipment/strings.invoice.tab_label', 'field' => 'items'],
        );
    }

    private static function chainUnspecific(): Check
    {
        return self::request('chain_unspecific', 'info', 'items',
            function (Builder $query, ReportFilters $filters): Builder {
                $live = str_replace('shipments.', 's.', InTransitChecks::voidSqlFor($filters));
                $lines = str_replace('shipments.', 'x.', InTransitChecks::voidSqlFor($filters));

                return self::scoped($query, $filters)->whereRaw('exists (select 1 from registered_order_purchase_request l join registered_orders o on o.id = l.registered_order_id and o.deleted_at is null where l.purchase_request_id = purchase_requests.id'
                    ." and exists (select 1 from shipments s where s.registered_order_id = l.registered_order_id and s.deleted_at is null and {$live})"
                    ." and not exists (select 1 from shipment_items n join shipments x on x.id = n.shipment_id and x.deleted_at is null and {$lines} where x.registered_order_id = l.registered_order_id))");
            },
            fn (): string => '-');
    }

    private static function sharedShipment(): Check
    {
        return self::request('shared_shipment', 'info', 'items',
            function (Builder $query, ReportFilters $filters): Builder {
                $live = str_replace('shipments.', 's.', InTransitChecks::voidSqlFor($filters));

                return self::scoped($query, $filters)->whereRaw('exists (select 1 from purchase_request_items a join registered_order_purchase_request la on la.purchase_request_id = a.purchase_request_id where a.purchase_request_id = purchase_requests.id and a.deleted_at is null'
                    ." and exists (select 1 from shipments s where s.registered_order_id = la.registered_order_id and s.deleted_at is null and {$live})"
                    .' and exists (select 1 from purchase_request_items b join purchase_requests pb on pb.id = b.purchase_request_id and pb.deleted_at is null join registered_order_purchase_request lb on lb.purchase_request_id = b.purchase_request_id'
                    .' where b.deleted_at is null and b.product_id = a.product_id and b.purchase_request_id <> a.purchase_request_id and lb.registered_order_id = la.registered_order_id))');
            },
            fn (): string => '-');
    }

    private static function unitMismatch(): Check
    {
        return self::request('unit_mismatch', 'info', 'items',
            fn (Builder $query, ReportFilters $filters): Builder => self::scoped($query, $filters)->whereRaw('exists (select 1 from purchase_request_items where purchase_request_items.purchase_request_id = purchase_requests.id and purchase_request_items.deleted_at is null and '
                .RequesterStages::shipmentExists("exists (select 1 from shipment_items z where z.shipment_id = shipments.id and z.product_id = purchase_request_items.product_id and lower(trim(coalesce(z.unit, ''))) <> lower(trim(coalesce(purchase_request_items.unit, ''))))", $filters)
                .')'),
            fn (): string => '-');
    }

    private static function missingRequiredByDate(): Check
    {
        return self::request('missing_required_by_date', 'info', 'required_by_date',
            fn (Builder $query, ReportFilters $filters): Builder => self::scoped($query, $filters)->whereNull('purchase_requests.required_by_date'),
            fn (): string => '-');
    }

    private static function shippedAboveRequested(): Check
    {
        return self::request('shipped_above_requested', 'warning', 'items',
            fn (Builder $query, ReportFilters $filters): Builder => self::scoped($query, $filters)->whereRaw('exists (select 1 from purchase_request_items where purchase_request_items.purchase_request_id = purchase_requests.id and purchase_request_items.deleted_at is null and '
                .RequesterStages::shippedAboveRequestedSql($filters).')'),
            fn (): string => '-');
    }

    private static function noOrderAfterDays(): Check
    {
        return new Check(
            key: 'purchase_request.no_order_after_n_days',
            model: PurchaseRequest::class,
            severity: 'warning',
            reports: ['requester'],
            query: function (Builder $query, ReportFilters $filters): Builder {
                $declined = app(ReportStages::class)->statusByName(PurchaseRequest::TYPE_PURCHASE_REQUEST, PurchaseRequestStatus::Declined->value)?->id;

                return self::scoped($query, $filters)
                    ->whereRaw('not '.RequesterStages::linkSql('purchase_requests.id'))
                    ->whereRaw('DATEDIFF('.SqlToday::literal().', purchase_requests.created_at) > ?', [$filters->threshold('supply')])
                    ->when($declined, fn (Builder $requests, int $id): Builder => $requests->where(fn (Builder $open): Builder => $open->whereNull('purchase_requests.status_id')->orWhere('purchase_requests.status_id', '<>', $id)));
            },
            offending: fn (PurchaseRequest $request): string => __('resources/report/strings.attention.days', ['count' => (int) $request->created_at->copy()->startOfDay()->diffInDays(today())]),
            expected: fn (mixed $record = null, ?ReportFilters $filters = null): string => __(self::LANG.'purchase_request.no_order_after_n_days.expected', ['days' => ($filters ?? new ReportFilters)->threshold('supply')]),
            fix: ['resource' => PurchaseRequestResource::class, 'tab' => self::TAB, 'field' => 'status_id'],
        );
    }

    private static function request(string $key, string $severity, string $field, Closure $query, Closure $offending): Check
    {
        return new Check(
            key: "purchase_request.{$key}",
            model: PurchaseRequest::class,
            severity: $severity,
            reports: ['requester'],
            query: $query,
            offending: $offending,
            expected: fn (): string => __(self::LANG."purchase_request.{$key}.expected"),
            fix: ['resource' => PurchaseRequestResource::class, 'tab' => self::TAB, 'field' => $field],
        );
    }
}
