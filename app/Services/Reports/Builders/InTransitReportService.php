<?php

namespace App\Services\Reports\Builders;

use App\Filament\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Models\Status;
use App\Services\Reports\Checks\InTransitChecks;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Support\SqlToday;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class InTransitReportService implements ReportService
{
    private const LANG = 'resources/report/strings.in_transit.';

    private const PRODUCTS_MAX_BYTES = 65535;

    private const STATES = ['arrived', 'in_transit', 'awaiting'];

    private const EXPORT_COLUMNS = [
        'shipment_no' => 'resources/shipment/strings.table.shipment_no',
        'part' => 'resources/shipment/strings.table.part',
        'registered_order' => 'resources/shipment/strings.table.registered_order',
        'seller' => 'resources/report/strings.filters.seller',
        'carrier' => 'resources/shipment/strings.table.carrier',
        'products' => self::LANG.'columns.products',
        'weight' => self::LANG.'columns.weight',
        'etd' => 'resources/shipment/strings.form.etd',
        'exit_date' => 'resources/shipment/strings.form.exit_date',
        'eta' => 'resources/shipment/strings.form.eta',
        'warehouse_date' => 'resources/shipment/strings.form.warehouse_date',
        'days_to_eta' => self::LANG.'columns.days_to_eta',
        'state' => self::LANG.'columns.state',
        'tracking_status' => 'resources/shipment/strings.table.tracking_status',
        'doc_status' => 'resources/shipment/strings.table.doc_status',
        'docs_progress' => 'resources/shipment/strings.table.docs_progress',
        'bl_number' => 'resources/shipment/strings.table.bl_number',
        'container_no' => 'resources/shipment/strings.form.container_no',
    ];

    public const PRODUCTS_SQL = 'COALESCE(si.products, roi.products)';

    public const WEIGHT_SQL = 'COALESCE(si.weight, roi.weight)';

    public function query(ReportFilters $filters): Builder
    {
        DB::statement('SET SESSION group_concat_max_len = '.self::PRODUCTS_MAX_BYTES);

        $ids = $this->scoped($filters)->select('shipments.id');
        $orderIds = $this->scoped($filters)->select('shipments.registered_order_id');

        return $this->applyQuick($this->scoped($filters), $filters)
            ->select('shipments.*')
            ->selectRaw('DATEDIFF(shipments.eta, '.SqlToday::literal().') as days_to_eta')
            ->selectRaw('(CASE WHEN '.InTransitChecks::arrivedSql()." THEN 'arrived' WHEN ".InTransitChecks::departedSql($filters)." THEN 'in_transit' ELSE 'awaiting' END) as state")
            ->selectRaw(self::PRODUCTS_SQL.' as products_text')
            ->selectRaw(self::WEIGHT_SQL.' as total_weight')
            ->selectRaw("(CASE WHEN si.shipment_id IS NOT NULL THEN 'invoice' WHEN roi.registered_order_id IS NOT NULL THEN 'order' ELSE NULL END) as weight_source")
            ->leftJoinSub($this->shipmentItems()->whereIn('shipment_items.shipment_id', $ids), 'si', fn (JoinClause $join) => $join->on('si.shipment_id', '=', 'shipments.id'))
            ->leftJoinSub($this->orderItems()->whereIn('registered_order_items.registered_order_id', $orderIds), 'roi', fn (JoinClause $join) => $join->on('roi.registered_order_id', '=', 'shipments.registered_order_id'))
            ->with(['registeredOrder:id,ro_number,seller_id', 'registeredOrder.sellerCompany:id,name,english_name', 'carrier:id,name,english_name', 'trackingStatus', 'docStatus']);
    }

    public function summary(ReportFilters $filters): array
    {
        $stats = $this->stats($filters);
        [$first, $second] = InTransitChecks::arriveWindows($filters);

        return [
            $this->card('awaiting', [
                $this->line(__(self::LANG.'lines.shipments'), $stats->awaiting, 'awaiting'),
                $this->line(__(self::LANG.'lines.without_eta'), $stats->awaiting_no_eta, 'awaiting_no_eta'),
                ...$this->hiddenLine($filters),
            ]),
            $this->card('in_transit', [
                $this->line(__(self::LANG.'lines.shipments'), $stats->in_transit, 'in_transit'),
                $this->line(__(self::LANG.'lines.without_eta'), $stats->in_transit_no_eta, 'in_transit_no_eta'),
                $this->line(__(self::LANG.'lines.arrived_unconfirmed'), $stats->arrived_unconfirmed, 'arrived_unconfirmed'),
            ]),
            $this->card('arriving', [
                $this->line(__(self::LANG.'lines.within_week', ['days' => $first]), $stats->arriving_week, 'arriving_week'),
                $this->line(__(self::LANG.'lines.two_weeks', ['from' => $first + 1, 'to' => $second]), $stats->arriving_two_weeks, 'arriving_two_weeks'),
            ]),
            $this->card('delayed', [
                $this->line(__(self::LANG.'lines.shipments'), $stats->delayed, 'delayed'),
                $this->line(__(self::LANG.'lines.over_14', ['days' => $filters->threshold('late_error')]), $stats->delayed_14, 'delayed_14'),
                ['label' => __(self::LANG.'lines.average_late'), 'value' => $stats->average_late === null ? '-' : (string) round((float) $stats->average_late, 1)],
            ]),
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        return $this->orderByEta($this->query($filters))->lazy()->map(fn (Shipment $shipment): array => $this->row($shipment));
    }

    public function exportLabels(): array
    {
        return array_map(fn (string $key): string => __($key), self::EXPORT_COLUMNS);
    }

    public function orderByEta(Builder $query): Builder
    {
        return $query->orderByRaw('shipments.eta is null')->orderBy('shipments.eta')->orderBy('shipments.id');
    }

    /**
     * @return array{labels: array<int, string>, counts: array<int, int>}
     */
    public function chartStages(ReportFilters $filters): array
    {
        $counts = $this->visible($filters)
            ->selectRaw('shipments.shipment_status_id as tracking_id, count(*) as total')
            ->groupBy('shipments.shipment_status_id')
            ->pluck('total', 'tracking_id');
        $stages = app(ReportStages::class)->ordered(Shipment::TYPE_TRACKING_STATUS);
        $known = $stages->map(fn (Status $status): int => (int) ($counts[$status->id] ?? 0));

        return [
            'labels' => [...$stages->map(fn (Status $status): string => (string) $status->getLocalizedNameAttribute())->all(), __(self::LANG.'charts.no_status')],
            'counts' => [...$known->all(), (int) $counts->sum() - $known->sum()],
        ];
    }

    /**
     * @return array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int>}>}
     */
    public function chartEtaBuckets(ReportFilters $filters): array
    {
        $buckets = $this->buckets($filters);
        [$first, $second] = InTransitChecks::arriveWindows($filters);
        $query = InTransitChecks::open(Shipment::query(), $filters);

        foreach (['in_transit' => InTransitChecks::inTransitSql($filters), 'awaiting' => InTransitChecks::awaitingSql($filters)] as $state => $stateSql) {
            foreach ($buckets as $key => $sql) {
                $query->selectRaw("coalesce(sum(case when {$stateSql} and {$sql} then 1 else 0 end), 0) as {$state}_{$key}");
            }
        }

        $row = $query->first();

        return [
            'labels' => array_map(fn (string $key): string => __(self::LANG."charts.buckets.{$key}", ['from' => $first + 1, 'to' => $second, 'days' => $first]), array_keys($buckets)),
            'datasets' => array_map(fn (string $state): array => [
                'label' => __(self::LANG."charts.{$state}"),
                'data' => array_map(fn (string $key): int => (int) $row->{"{$state}_{$key}"}, array_keys($buckets)),
            ], ['in_transit', 'awaiting']),
        ];
    }

    private function buckets(ReportFilters $filters): array
    {
        $today = SqlToday::literal();
        [$first, $second] = InTransitChecks::arriveWindows($filters);
        $next = $first + 1;

        return [
            'delayed' => "shipments.eta is not null and shipments.eta < {$today}",
            'week' => "shipments.eta is not null and DATEDIFF(shipments.eta, {$today}) between 0 and {$first}",
            'two_weeks' => "shipments.eta is not null and DATEDIFF(shipments.eta, {$today}) between {$next} and {$second}",
            'later' => "shipments.eta is not null and DATEDIFF(shipments.eta, {$today}) > {$second}",
            'no_eta' => 'shipments.eta is null',
        ];
    }

    private function row(Shipment $shipment): array
    {
        return [
            'shipment_no' => $shipment->shipment_no,
            'part' => $shipment->part,
            'registered_order' => $shipment->registeredOrder?->ro_number,
            'seller' => $shipment->registeredOrder?->sellerCompany?->getLocalizedNameAttribute(),
            'carrier' => $shipment->carrier?->getLocalizedNameAttribute(),
            'products' => $shipment->products_text,
            'weight' => $shipment->total_weight !== null ? delimiter($shipment->total_weight) : null,
            'etd' => $this->date($shipment->etd),
            'exit_date' => $this->date($shipment->exit_date),
            'eta' => $this->date($shipment->eta),
            'warehouse_date' => $this->date($shipment->warehouse_date),
            'days_to_eta' => $shipment->days_to_eta,
            'state' => __(self::LANG.'states.'.$shipment->state),
            'tracking_status' => $shipment->trackingStatus?->getLocalizedNameAttribute(),
            'doc_status' => $shipment->docStatus?->getLocalizedNameAttribute(),
            'docs_progress' => ($stats = ShipmentResource::docsProgressStats($shipment)) ? "{$stats['received']}/{$stats['total']}" : null,
            'bl_number' => $shipment->bl_number,
            'container_no' => $shipment->container_no,
        ];
    }

    private function quickSql(ReportFilters $filters): ?string
    {
        $inTransit = InTransitChecks::inTransitSql($filters);
        $awaiting = InTransitChecks::awaitingSql($filters);
        [$first, $second] = InTransitChecks::arriveWindows($filters);

        return match ($filters->quick) {
            'awaiting' => $awaiting,
            'awaiting_no_eta' => "({$awaiting} and shipments.eta is null)",
            'in_transit' => $inTransit,
            'in_transit_no_eta' => "({$inTransit} and shipments.eta is null)",
            'arrived_unconfirmed' => InTransitChecks::unconfirmedSql(),
            'arriving_week' => InTransitChecks::arrivingSql(0, $first, $filters),
            'arriving_two_weeks' => InTransitChecks::arrivingSql($first + 1, $second, $filters),
            'delayed' => InTransitChecks::delayedSql(),
            'delayed_14' => InTransitChecks::lateOverSql($filters),
            'in_transit_delayed' => "({$inTransit} and ".InTransitChecks::delayedSql().')',
            default => null,
        };
    }

    public static function sellerNameSql(): string
    {
        $column = app()->getLocale() === 'fa' ? 'name' : 'english_name';

        return "(select companies.{$column} from registered_orders join companies on companies.id = registered_orders.seller_id where registered_orders.id = shipments.registered_order_id)";
    }

    public static function carrierNameSql(): string
    {
        $column = app()->getLocale() === 'fa' ? 'name' : 'english_name';

        return "(select companies.{$column} from companies where companies.id = shipments.company_id)";
    }

    public function searchState(Builder $query, string $search, ReportFilters $filters): Builder
    {
        $term = mb_strtolower(trim($search));
        $sql = [
            'arrived' => InTransitChecks::arrivedSql(),
            'in_transit' => InTransitChecks::inTransitSql($filters),
            'awaiting' => InTransitChecks::awaitingSql($filters),
        ];
        $matches = array_filter(
            self::STATES,
            fn (string $state): bool => $term !== '' && str_contains(mb_strtolower(__(self::LANG."states.{$state}")), $term),
        );

        return $query->whereRaw($matches === [] ? '(0 = 1)' : '('.implode(' or ', array_map(fn (string $state): string => $sql[$state], $matches)).')');
    }

    private function hiddenLine(ReportFilters $filters): array
    {
        if ($filters->includeVoid) {
            return [];
        }

        return [['label' => __(self::LANG.'lines.void_hidden'), 'value' => (string) InTransitChecks::hidden(Shipment::query(), $filters)->count()]];
    }

    private function applyQuick(Builder $query, ReportFilters $filters): Builder
    {
        $sql = $this->quickSql($filters);

        if ($sql !== null) {
            return $query->whereRaw($sql);
        }

        return InTransitChecks::showsArrived($filters) ? $query : $query->whereRaw(InTransitChecks::notArrivedSql());
    }

    private function scoped(ReportFilters $filters): Builder
    {
        return InTransitChecks::scoped(Shipment::query(), $filters);
    }

    private function visible(ReportFilters $filters): Builder
    {
        return InTransitChecks::showsArrived($filters) ? $this->scoped($filters) : InTransitChecks::open(Shipment::query(), $filters);
    }

    private function stats(ReportFilters $filters): object
    {
        $inTransit = InTransitChecks::inTransitSql($filters);
        $awaiting = InTransitChecks::awaitingSql($filters);
        $delayed = InTransitChecks::delayedSql();
        [$first, $second] = InTransitChecks::arriveWindows($filters);
        $sums = [
            'awaiting' => $awaiting,
            'awaiting_no_eta' => "{$awaiting} and shipments.eta is null",
            'in_transit' => $inTransit,
            'in_transit_no_eta' => "{$inTransit} and shipments.eta is null",
            'arrived_unconfirmed' => InTransitChecks::unconfirmedSql(),
            'arriving_week' => InTransitChecks::arrivingSql(0, $first, $filters),
            'arriving_two_weeks' => InTransitChecks::arrivingSql($first + 1, $second, $filters),
            'delayed' => $delayed,
            'delayed_14' => InTransitChecks::lateOverSql($filters),
        ];
        $query = $this->scoped($filters);

        foreach ($sums as $alias => $sql) {
            $query->selectRaw("coalesce(sum(case when {$sql} then 1 else 0 end), 0) as `{$alias}`");
        }

        return $query
            ->selectRaw("avg(case when {$delayed} then DATEDIFF(".SqlToday::literal().', shipments.eta) end) as average_late')
            ->first();
    }

    private function shipmentItems(): QueryBuilder
    {
        $column = $this->nameColumn();

        return DB::table('shipment_items')
            ->leftJoin('products', 'products.id', '=', 'shipment_items.product_id')
            ->select('shipment_id')
            ->selectRaw("GROUP_CONCAT(DISTINCT COALESCE(products.{$column}, shipment_items.{$column}) SEPARATOR ', ') as products")
            ->selectRaw('SUM(shipment_items.gross_weight) as weight')
            ->groupBy('shipment_id');
    }

    private function orderItems(): QueryBuilder
    {
        $column = $this->nameColumn();

        return DB::table('registered_order_items')
            ->leftJoin('products', 'products.id', '=', 'registered_order_items.product_id')
            ->whereNull('registered_order_items.deleted_at')
            ->select('registered_order_id')
            ->selectRaw("GROUP_CONCAT(DISTINCT products.{$column} SEPARATOR ', ') as products")
            ->selectRaw('SUM(registered_order_items.gross_weight) as weight')
            ->groupBy('registered_order_id');
    }

    private function nameColumn(): string
    {
        return app()->getLocale() === 'fa' ? 'name' : 'english_name';
    }

    private function card(string $key, array $lines): array
    {
        return ['key' => $key, 'label' => __(self::LANG."cards.{$key}"), 'lines' => $lines, 'tone' => null];
    }

    private function line(string $label, mixed $value, string $quick): array
    {
        return ['label' => $label, 'value' => (string) (int) $value, 'quick' => $quick];
    }

    private function date(mixed $date): ?string
    {
        return $date ? adaptiveDate($date) : null;
    }
}
