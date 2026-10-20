<?php

namespace App\Services\Reports\Checks;

use App\Filament\Resources\ShipmentResource;
use App\Models\Product;
use App\Models\Shipment;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\ReportDates;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Support\SqlToday;
use Closure;
use Illuminate\Database\Eloquent\Builder;

final class InTransitChecks
{
    public const STUCK_DAYS = 30;

    public const DATE_BASES = [
        'eta' => 'shipments.eta',
        'etd' => 'shipments.etd',
        'exit_date' => 'shipments.exit_date',
        'warehouse_date' => 'shipments.warehouse_date',
    ];

    public const HISTORY_AT = '(select max(created_at) from status_histories where statusable_type = ? and statusable_id = shipments.id and field = ?)';

    private const EXECUTIVE_KEYS = ['in_transit_missing_eta', 'eta_before_etd', 'overdue_eta', 'no_invoice'];

    private const VOID_STATUSES = ['Cancelled', 'Rejected'];

    private const LOGISTICS_TAB = 'resources/shipment/strings.form.tab_logistics';

    private const LANG = 'resources/report/strings.attention.';

    /**
     * @return array<int, Check>
     */
    public static function all(): array
    {
        return [
            self::missingEta(),
            self::missingEtd(),
            self::etaBeforeEtd(),
            self::overdueEta(),
            self::arrivedWithoutWarehouseDate(),
            self::departedWithoutExitDate(),
            self::noInvoice(),
            self::stuckInStage(),
            self::warehouseBeforeExitDate(),
            self::missingCarrier(),
            self::missingBlNumber(),
        ];
    }

    public static function arrivedSql(): string
    {
        return '(shipments.warehouse_date is not null or '.self::trackingArrivedSql().')';
    }

    public static function trackingArrivedSql(): string
    {
        $ids = array_map('intval', CustomChecks::arrivedOrLaterIds());

        return $ids === [] ? '(0 = 1)' : '(coalesce(shipments.shipment_status_id in ('.implode(',', $ids).'), 0) = 1)';
    }

    public static function trackingInTransitSql(): string
    {
        $id = app(ReportStages::class)->inTransit()?->id;

        return $id ? '(shipments.shipment_status_id <=> '.(int) $id.')' : '(0 = 1)';
    }

    public static function notArrivedSql(): string
    {
        return '(not '.self::arrivedSql().')';
    }

    public static function departedSql(?ReportFilters $filters = null): string
    {
        $tracking = self::trackingInTransitSql();

        return match ($filters?->departureBasis) {
            'etd_too' => "(shipments.exit_date is not null or shipments.etd is not null or {$tracking})",
            'tracking_only' => $tracking,
            default => "(shipments.exit_date is not null or {$tracking})",
        };
    }

    public static function inTransitSql(?ReportFilters $filters = null): string
    {
        return '('.self::notArrivedSql().' and '.self::departedSql($filters).')';
    }

    public static function awaitingSql(?ReportFilters $filters = null): string
    {
        return '('.self::notArrivedSql().' and not '.self::departedSql($filters).')';
    }

    public static function delayedSql(): string
    {
        return '('.self::notArrivedSql().' and shipments.eta is not null and shipments.eta < '.SqlToday::literal().')';
    }

    public static function lateOverSql(ReportFilters $filters): string
    {
        return '('.self::delayedSql().' and DATEDIFF('.SqlToday::literal().', shipments.eta) > '.$filters->threshold('late_error').')';
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function arriveWindows(?ReportFilters $filters = null): array
    {
        $first = $filters?->threshold('arrive_1') ?? ReportFilters::THRESHOLD_DEFAULTS['arrive_1'];
        $second = $filters?->threshold('arrive_2') ?? ReportFilters::THRESHOLD_DEFAULTS['arrive_2'];

        return [$first, max($second, $first + 1)];
    }

    public static function arrivingSql(int $from, int $to, ?ReportFilters $filters = null): string
    {
        return '('.self::inTransitSql($filters).' and shipments.eta is not null and DATEDIFF(shipments.eta, '.SqlToday::literal().") between {$from} and {$to})";
    }

    public static function unconfirmedSql(): string
    {
        return '('.self::trackingArrivedSql().' and shipments.warehouse_date is null)';
    }

    public static function voidSql(): string
    {
        $stages = app(ReportStages::class);
        $ids = collect(self::VOID_STATUSES)
            ->map(fn (string $name): ?int => $stages->statusByName(Shipment::TYPE_SHIPMENT_STATUS, $name)?->id)
            ->filter()
            ->map(fn (int $id): int => $id)
            ->all();

        return $ids === [] ? '(1 = 1)' : '(shipments.status_id is null or shipments.status_id not in ('.implode(',', $ids).'))';
    }

    public static function voidSqlFor(ReportFilters $filters): string
    {
        return $filters->includeVoid ? '(1 = 1)' : self::voidSql();
    }

    public static function scoped(Builder $query, ReportFilters $filters): Builder
    {
        return self::narrowed($query, $filters)->whereRaw(self::voidSqlFor($filters));
    }

    public static function hidden(Builder $query, ReportFilters $filters): Builder
    {
        return self::narrowed($query, $filters)
            ->whereRaw('not '.self::voidSql())
            ->when(! self::showsArrived($filters), fn (Builder $shipments): Builder => $shipments->whereRaw(self::notArrivedSql()));
    }

    public static function showsArrived(ReportFilters $filters): bool
    {
        return $filters->showArrived || array_intersect($filters->statusIds, array_map('intval', CustomChecks::arrivedOrLaterIds())) !== [];
    }

    public static function narrowed(Builder $query, ReportFilters $filters): Builder
    {
        $query
            ->when($filters->registeredOrderId, fn (Builder $shipments, int $id): Builder => $shipments->where('shipments.registered_order_id', $id))
            ->when($filters->sellerId, fn (Builder $shipments, int $id): Builder => $shipments->whereHas('registeredOrder', fn (Builder $order): Builder => $order->where('registered_orders.seller_id', $id)))
            ->when($filters->buyerId, fn (Builder $shipments, int $id): Builder => $shipments->whereHas('registeredOrder', fn (Builder $order): Builder => $order->where('registered_orders.buyer_id', $id)))
            ->tap(fn (Builder $shipments): Builder => self::productScoped($shipments, $filters))
            ->when($filters->companyId, fn (Builder $shipments, int $id): Builder => $shipments->where('shipments.company_id', $id))
            ->when($filters->statusIds, fn (Builder $shipments, array $ids): Builder => $shipments->whereIn('shipments.shipment_status_id', $ids))
            ->when($filters->shipmentStatusIds, fn (Builder $shipments, array $ids): Builder => $shipments->whereIn('shipments.status_id', $ids))
            ->when($filters->docStatusId, fn (Builder $shipments, int $id): Builder => $shipments->where('shipments.doc_status_id', $id));

        return ReportDates::apply($query, $filters, self::DATE_BASES, 'eta');
    }

    public static function productScoped(Builder $query, ReportFilters $filters): Builder
    {
        return $query
            ->when($filters->productId, fn (Builder $shipments, int $id): Builder => $shipments->where(fn (Builder $items): Builder => $items
                ->whereHas('items', fn (Builder $item): Builder => $item->where('shipment_items.product_id', $id))
                ->orWhereHas('registeredOrder.items', fn (Builder $item): Builder => $item->where('registered_order_items.product_id', $id))))
            ->when($filters->categoryId, function (Builder $shipments) use ($filters): Builder {
                $products = Product::query()->whereIn('category_id', $filters->categoryIds())->pluck('id')->all();

                return $shipments->where(fn (Builder $items): Builder => $items
                    ->whereHas('items', fn (Builder $item): Builder => $item->whereIn('shipment_items.product_id', $products))
                    ->orWhereHas('registeredOrder.items', fn (Builder $item): Builder => $item->whereIn('registered_order_items.product_id', $products)));
            });
    }

    public static function open(Builder $query, ReportFilters $filters): Builder
    {
        return self::scoped($query, $filters)->whereRaw(self::notArrivedSql());
    }

    private static function missingEta(): Check
    {
        return self::make('in_transit_missing_eta', 'warning', 'eta',
            fn (Builder $query, ReportFilters $filters): Builder => self::open($query, $filters)
                ->whereRaw(self::inTransitSql($filters))
                ->whereNull('shipments.eta'),
            fn (): string => '-');
    }

    private static function missingEtd(): Check
    {
        return self::make('in_transit_missing_etd', 'info', 'etd',
            fn (Builder $query, ReportFilters $filters): Builder => self::open($query, $filters)
                ->whereRaw(self::inTransitSql($filters))
                ->whereNull('shipments.etd'),
            fn (): string => '-');
    }

    private static function etaBeforeEtd(): Check
    {
        return self::make('eta_before_etd', 'error', 'eta',
            fn (Builder $query, ReportFilters $filters): Builder => self::open($query, $filters)
                ->whereNotNull('shipments.eta')
                ->whereNotNull('shipments.etd')
                ->whereColumn('shipments.eta', '<', 'shipments.etd'),
            fn (Shipment $shipment): string => self::date($shipment->eta).' < '.self::date($shipment->etd));
    }

    private static function overdueEta(): Check
    {
        return self::make('overdue_eta', 'warning', 'eta',
            fn (Builder $query, ReportFilters $filters): Builder => self::open($query, $filters)
                ->select('shipments.*')
                ->selectRaw('DATEDIFF('.SqlToday::literal().', shipments.eta) as days_late')
                ->whereRaw(self::delayedSql()),
            fn (Shipment $shipment): string => __(self::LANG.'days', ['count' => (int) $shipment->days_late]));
    }

    private static function arrivedWithoutWarehouseDate(): Check
    {
        return self::make('arrived_without_warehouse_date', 'warning', 'warehouse_date',
            fn (Builder $query, ReportFilters $filters): Builder => self::scoped($query, $filters)->whereRaw(self::unconfirmedSql()),
            fn (): string => '-');
    }

    private static function departedWithoutExitDate(): Check
    {
        return self::make('departed_without_exit_date', 'warning', 'exit_date',
            fn (Builder $query, ReportFilters $filters): Builder => self::open($query, $filters)
                ->whereRaw(self::trackingInTransitSql())
                ->whereNull('shipments.exit_date')
                ->whereNull('shipments.etd'),
            fn (): string => '-');
    }

    private static function noInvoice(): Check
    {
        return new Check(
            key: 'shipment.no_invoice',
            model: Shipment::class,
            severity: 'warning',
            reports: self::reportsFor('no_invoice'),
            query: fn (Builder $base, ReportFilters $filters): Builder => self::open($base, $filters)
                ->whereRaw(self::inTransitSql($filters))
                ->whereDoesntHave('items'),
            offending: fn (): string => '-',
            expected: fn (): string => __(self::LANG.'checks.shipment.no_invoice.expected'),
            fix: ['resource' => ShipmentResource::class, 'tab' => 'resources/shipment/strings.invoice.tab_label', 'field' => 'items'],
        );
    }

    private static function warehouseBeforeExitDate(): Check
    {
        return self::make('warehouse_before_exit_date', 'error', 'warehouse_date',
            fn (Builder $query, ReportFilters $filters): Builder => self::scoped($query, $filters)
                ->whereNotNull('shipments.warehouse_date')
                ->whereNotNull('shipments.exit_date')
                ->whereColumn('shipments.warehouse_date', '<', 'shipments.exit_date'),
            fn (Shipment $shipment): string => self::date($shipment->warehouse_date).' < '.self::date($shipment->exit_date));
    }

    private static function missingCarrier(): Check
    {
        return self::make('missing_carrier', 'warning', 'company_id',
            fn (Builder $query, ReportFilters $filters): Builder => self::open($query, $filters)
                ->whereRaw(self::inTransitSql($filters))
                ->whereRaw('(shipments.company_id is null or not exists (select 1 from companies where companies.id = shipments.company_id and companies.is_active = 1 and companies.deleted_at is null))'),
            fn (): string => '-',
            'resources/shipment/strings.form.tab_general');
    }

    private static function missingBlNumber(): Check
    {
        return self::make('missing_bl_number', 'info', 'bl_number',
            fn (Builder $query, ReportFilters $filters): Builder => self::open($query, $filters)
                ->whereRaw(self::inTransitSql($filters))
                ->where(fn (Builder $shipments): Builder => $shipments->whereNull('shipments.bl_number')->orWhere('shipments.bl_number', '')),
            fn (): string => '-');
    }

    private static function stuckInStage(): Check
    {
        return new Check(
            key: 'shipment.stuck_in_stage',
            model: Shipment::class,
            severity: 'warning',
            reports: ['in_transit'],
            query: function (Builder $base, ReportFilters $filters): Builder {
                $threshold = $filters->threshold('stuck');
                $days = 'DATEDIFF('.SqlToday::literal().', COALESCE('.self::HISTORY_AT.', shipments.updated_at, shipments.created_at))';

                return self::open($base, $filters)
                    ->select('shipments.*')
                    ->selectRaw(self::HISTORY_AT.' as history_at', [Shipment::class, 'shipment_status_id'])
                    ->selectRaw("{$days} as days_in_stage", [Shipment::class, 'shipment_status_id'])
                    ->whereNotNull('shipments.shipment_status_id')
                    ->whereRaw("{$days} > ?", [Shipment::class, 'shipment_status_id', $threshold]);
            },
            offending: fn (Shipment $shipment): string => self::stuckText($shipment),
            expected: fn (?Shipment $shipment = null, ?ReportFilters $filters = null): string => __(self::LANG.'checks.shipment.stuck_in_stage.expected', ['days' => ($filters ?? new ReportFilters)->threshold('stuck')]),
            fix: ['resource' => ShipmentResource::class, 'tab' => self::LOGISTICS_TAB, 'field' => 'shipment_status_id'],
        );
    }

    private static function stuckText(Shipment $shipment): string
    {
        $text = __(self::LANG.'days', ['count' => (int) $shipment->days_in_stage]);

        return $shipment->history_at === null ? $text.' '.__(self::LANG.'no_history_note') : $text;
    }

    private static function date(mixed $date): string
    {
        return $date ? adaptiveDate($date) : '-';
    }

    private static function reportsFor(string $key): array
    {
        return in_array($key, self::EXECUTIVE_KEYS, true) ? ['in_transit', 'executive'] : ['in_transit'];
    }

    private static function make(string $key, string $severity, string $field, Closure $query, Closure $offending, string $tab = self::LOGISTICS_TAB): Check
    {
        return new Check(
            key: "shipment.{$key}",
            model: Shipment::class,
            severity: $severity,
            reports: self::reportsFor($key),
            query: $query,
            offending: $offending,
            expected: fn (): string => __(self::LANG."checks.shipment.{$key}.expected"),
            fix: ['resource' => ShipmentResource::class, 'tab' => $tab, 'field' => $field],
        );
    }
}
