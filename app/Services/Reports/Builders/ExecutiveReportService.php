<?php

namespace App\Services\Reports\Builders;

use App\Filament\Pages\Reports\ReportPage;
use App\Models\Shipment;
use App\Services\Reports\Checks\InTransitChecks;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Support\SqlToday;
use App\Services\Reports\Support\TransitLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExecutiveReportService implements ReportService
{
    private const LANG = 'resources/report/strings.executive.';

    private const GROUP_CONCAT_BYTES = 65535;

    private const CHUNK = 1000;

    private const STACKS = [0 => 0, 1 => 1, 2 => 2, 3 => 2, 4 => 3];

    private const R4_FILTERS = ['date_from', 'date_until', 'date_basis', 'include_undated', 'seller_id', 'buyer_id', 'registered_order_id', 'product_id', 'category_id', 'company_id', 'status_id', 'shipment_status_id', 'doc_status_id', 'include_void', 'departure_basis', 'th_arrive_1', 'th_arrive_2'];

    private const SHIPMENT_COLUMNS = [
        'shipment_no' => 'resources/shipment/strings.table.shipment_no',
        'part' => 'resources/shipment/strings.table.part',
        'registered_order' => 'resources/shipment/strings.table.registered_order',
        'seller' => 'resources/report/strings.filters.seller',
        'carrier' => 'resources/shipment/strings.table.carrier',
        'products' => self::LANG.'columns.products',
        'value' => self::LANG.'columns.value',
        'eur' => self::LANG.'columns.eur',
        'source' => self::LANG.'columns.source',
        'supply' => self::LANG.'columns.supply',
        'supplied_percent' => self::LANG.'columns.supplied_percent',
        'supplied_remaining' => self::LANG.'columns.supplied_remaining',
        'weight' => self::LANG.'columns.weight',
        'etd' => 'resources/shipment/strings.form.etd',
        'eta' => 'resources/shipment/strings.form.eta',
        'days_to_eta' => 'resources/report/strings.in_transit.columns.days_to_eta',
        'tracking_status' => 'resources/shipment/strings.table.tracking_status',
        'flags' => self::LANG.'columns.flags',
    ];

    private const PRODUCT_COLUMNS = [
        'category' => self::LANG.'columns.category',
        'product' => self::LANG.'columns.product',
        'quantity' => self::LANG.'columns.quantity',
        'net_weight' => self::LANG.'columns.net_weight',
        'gross_weight' => self::LANG.'columns.gross_weight',
        'eur' => self::LANG.'columns.eur',
        'shipments' => self::LANG.'columns.shipments',
        'eta' => 'resources/shipment/strings.form.eta',
        'supply' => self::LANG.'columns.supply',
    ];

    private array $currencies = [];

    public static function productsSql(): string
    {
        $column = self::nameColumn();

        return "COALESCE((select GROUP_CONCAT(DISTINCT COALESCE(p.{$column}, si.{$column}) SEPARATOR ', ') from shipment_items si left join products p on p.id = si.product_id where si.shipment_id = shipments.id), (select GROUP_CONCAT(DISTINCT p.{$column} SEPARATOR ', ') from registered_order_items roi join products p on p.id = roi.product_id where roi.registered_order_id = shipments.registered_order_id and roi.deleted_at is null))";
    }

    public static function sellerNameSql(): string
    {
        $column = self::nameColumn();

        return "(select companies.{$column} from registered_orders join companies on companies.id = registered_orders.seller_id where registered_orders.id = shipments.registered_order_id)";
    }

    public static function carrierNameSql(): string
    {
        $column = self::nameColumn();

        return "(select companies.{$column} from companies where companies.id = shipments.company_id)";
    }

    public function query(ReportFilters $filters): Builder
    {
        DB::statement('SET SESSION group_concat_max_len = '.self::GROUP_CONCAT_BYTES);

        $query = TransitLedger::population($filters)
            ->leftJoinSub(TransitLedger::derivedSql(TransitLedger::rows($filters)['rows']), 'led', fn (JoinClause $join) => $join->on('led.led_shipment_id', '=', 'shipments.id'))
            ->select('shipments.*', 'led.*')
            ->selectRaw('DATEDIFF(shipments.eta, '.SqlToday::literal().') as days_to_eta')
            ->selectRaw(self::productsSql().' as products_text')
            ->with(['registeredOrder:id,ro_number,seller_id,currency_id', 'registeredOrder.sellerCompany:id,name,english_name', 'carrier:id,name,english_name', 'trackingStatus']);

        $sql = $this->quickSql($filters);

        return $sql === null ? $query : $query->whereRaw($sql);
    }

    public function summary(ReportFilters $filters): array
    {
        $ledger = TransitLedger::rows($filters);
        $rows = collect($ledger['rows']);
        $stats = $this->stats($filters);

        return [
            $this->card('cargo', $this->cargoLines($stats, $rows, $ledger['over'], $filters)),
            $this->card('value', $ledger['over'] ? $this->overLines() : $this->valueLines($rows)),
            $this->card('cover', $ledger['over'] ? $this->overLines() : $this->coverLines($rows, $filters)),
            $this->card('suppliers', $ledger['over'] ? $this->overLines() : $this->supplierLines($rows, $filters)),
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        if ($filters->viewMode === 'product') {
            return $this->productRows($filters)->map(fn (array $line): array => $this->productExportRow($line));
        }

        return $this->query($filters)->orderBy('shipments.id')->lazy()->map(fn (Shipment $shipment): array => $this->shipmentExportRow($shipment));
    }

    public function exportLabels(): array
    {
        return array_map(fn (string $key): string => __($key), self::SHIPMENT_COLUMNS);
    }

    public function exportLabelsFor(ReportFilters $filters): array
    {
        return $filters->viewMode === 'product'
            ? array_map(fn (string $key): string => __($key), self::PRODUCT_COLUMNS)
            : $this->exportLabels();
    }

    /**
     * @return array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, float>}>}
     */
    public function chartByCategory(ReportFilters $filters): array
    {
        $counted = $this->counted($filters);
        $roots = $this->rootMap();
        $bars = [];
        $add = function (string $bar, array $row, float $amount) use (&$bars): void {
            if ($amount === 0.0) {
                return;
            }

            $bars[$bar] ??= [0.0, 0.0, 0.0, 0.0];
            $bars[$bar][self::STACKS[$row['supply']]] += $amount;
        };

        foreach ($counted as $row) {
            $add('charges', $row, $row['charges'] * $row['rate']);
        }

        foreach ($this->invoiceLines($counted->where('estimated', false)->keys()->all(), true) as $line) {
            $row = $counted[$line->shipment_id];
            $add($this->bar($line, $roots), $row, (float) $line->amount * $row['rate']);
        }

        $estimated = $counted->where('estimated', true);

        foreach ($this->orderLines($estimated->pluck('order_id')->unique()->filter()->all(), true)->groupBy('registered_order_id') as $orderId => $lines) {
            foreach ($estimated->where('order_id', $orderId) as $row) {
                foreach ($lines as $line) {
                    $add($this->bar($line, $roots), $row, (float) $line->amount * $row['share'] * $row['rate']);
                }
            }
        }

        return $this->barChart($bars);
    }

    /**
     * @return array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, float>}>, excluded: array<string, float>, excluded_data: int}
     */
    public function chartByEta(ReportFilters $filters): array
    {
        [$first, $second] = InTransitChecks::arriveWindows($filters);
        $keys = ['delayed', 'week', 'two_weeks', 'later', 'no_eta'];
        $data = ['invoice' => array_fill_keys($keys, 0.0), 'estimated' => array_fill_keys($keys, 0.0)];
        $ledger = collect(TransitLedger::rows($filters)['rows']);

        foreach ($ledger->where('reason', 0) as $row) {
            $days = $row['eta'] === null ? null : (int) today()->diffInDays(Carbon::parse($row['eta']), false);
            $bucket = match (true) {
                $days === null => 'no_eta',
                $days < 0 => 'delayed',
                $days <= $first => 'week',
                $days <= $second => 'two_weeks',
                default => 'later',
            };
            $data[$row['estimated'] ? 'estimated' : 'invoice'][$bucket] += $row['eur'];
        }

        $names = $this->currencyNames();

        return [
            'labels' => array_map(fn (string $key): string => __(self::LANG."charts.buckets.{$key}", ['from' => $first + 1, 'to' => $second, 'days' => $first]), $keys),
            'datasets' => [['label' => __(self::LANG.'charts.by_eta.heading'), 'data' => array_map(fn (float $invoice, float $estimated): float => round($invoice + $estimated, 2), array_values($data['invoice']), array_values($data['estimated']))]],
            'excluded' => $ledger->where('reason', 5)->groupBy('currency_id')->mapWithKeys(fn (Collection $rows, mixed $currency): array => [$names[$currency] ?? '-' => (float) $rows->sum('value')])->all(),
            'excluded_data' => $ledger->whereBetween('reason', [1, 4])->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function productRows(ReportFilters $filters): Collection
    {
        $counted = $this->counted($filters);
        $roots = $this->categoryPaths();
        $lines = [];
        $add = function (object $line, array $row, float $factor) use (&$lines): void {
            $key = $line->product_id !== null ? 'p'.$line->product_id : 'n'.mb_strtolower((string) ($line->label ?? ''));
            $lines[$key] ??= ['key' => $key, 'product_id' => $line->product_id, 'label' => $line->label ?? null, 'category_id' => $line->category_id, 'quantity' => 0.0, 'units' => [], 'net_weight' => 0.0, 'gross_weight' => 0.0, 'eur' => 0.0, 'ids' => [], 'eta' => null, 'supplies' => []];
            $lines[$key]['quantity'] += (float) $line->quantity * $factor;
            $lines[$key]['net_weight'] += (float) $line->net_weight * $factor;
            $lines[$key]['gross_weight'] += (float) $line->gross_weight * $factor;
            $lines[$key]['eur'] += (float) $line->amount * $factor * $row['rate'];
            $lines[$key]['units'][(string) $line->unit] = true;
            $lines[$key]['ids'][$row['id']] = true;
            $lines[$key]['supplies'][$row['supply']] = true;
            $lines[$key]['eta'] = $row['eta'] !== null && ($lines[$key]['eta'] === null || $row['eta'] < $lines[$key]['eta']) ? $row['eta'] : $lines[$key]['eta'];
        };

        foreach ($this->invoiceLines($counted->where('estimated', false)->keys()->all(), false) as $line) {
            $add($line, $counted[$line->shipment_id], 1.0);
        }

        $estimated = $counted->where('estimated', true);

        foreach ($this->orderLines($estimated->pluck('order_id')->unique()->filter()->all(), false)->groupBy('registered_order_id') as $orderId => $orderLines) {
            foreach ($estimated->where('order_id', $orderId) as $row) {
                foreach ($orderLines as $line) {
                    $add($line, $row, (float) $row['share']);
                }
            }
        }

        $products = $this->names('products', collect($lines)->pluck('product_id')->filter()->all());

        $result = collect($lines)->map(fn (array $line): array => [
            'key' => $line['key'],
            'category' => $this->pathOf($line['category_id'], $roots),
            'product' => $line['product_id'] !== null ? ($products[$line['product_id']] ?? '-') : (($line['label'] ?? '') !== '' ? $line['label'] : __(self::LANG.'columns.no_product')),
            'no_product' => $line['product_id'] === null,
            'quantity' => $line['quantity'],
            'unit' => count($line['units']) === 1 ? (string) array_key_first($line['units']) : '',
            'net_weight' => $line['net_weight'],
            'gross_weight' => $line['gross_weight'],
            'eur' => $line['eur'],
            'shipments' => count($line['ids']),
            'eta' => $line['eta'],
            'supply' => count($line['supplies']) === 1 ? array_key_first($line['supplies']) : -1,
            'footer' => false,
        ])->sortByDesc('eur')->values();

        $charges = $counted->sum(fn (array $row): float => $row['charges'] * $row['rate']);

        return $result->push([
            'key' => 'charges', 'category' => '', 'product' => __(self::LANG.'columns.charges'), 'no_product' => false, 'quantity' => null, 'unit' => '', 'net_weight' => null, 'gross_weight' => null,
            'eur' => $charges, 'shipments' => null, 'eta' => null, 'supply' => null, 'footer' => true,
        ]);
    }

    public function supplyLabel(?int $supply): string
    {
        return match (true) {
            $supply === null => '-',
            $supply === -1 => __(self::LANG.'supply.mixed'),
            default => __(self::LANG.'supply.'.TransitLedger::SUPPLY[$supply]),
        };
    }

    public function sourceLabel(object $shipment): string
    {
        return match (true) {
            $shipment->led_reason === null => '-',
            (int) $shipment->led_reason !== 0 => __(self::LANG.'source.excluded', ['reason' => __(self::LANG.'reasons.'.(int) $shipment->led_reason)]),
            (bool) $shipment->led_estimated => __(self::LANG.'source.estimated'),
            default => __(self::LANG.'source.invoice'),
        };
    }

    public function flagsText(int $flags): string
    {
        return collect(TransitLedger::FLAGS)
            ->filter(fn (int $bit): bool => (bool) ($flags & $bit))
            ->keys()
            ->map(fn (string $name): string => __(self::LANG."flags.{$name}"))
            ->implode(', ');
    }

    public function currencyNames(): array
    {
        return $this->currencies[app()->getLocale()] ??= $this->names('currencies');
    }

    public function drillUrl(string $key, array $query): ?string
    {
        $page = ReportPage::registry()[$key] ?? null;

        return $page && $page::canAccess() ? $page::getUrl($query) : null;
    }

    private function quickSql(ReportFilters $filters): ?string
    {
        if ($filters->quick !== null && preg_match('/^supply_(full|partial|none|allocated|unknown)$/', $filters->quick, $match)) {
            return '(led.led_supply = '.array_search($match[1], TransitLedger::SUPPLY, true).')';
        }

        return match ($filters->quick) {
            'in_transit' => '(1 = 1)',
            'delayed' => InTransitChecks::delayedSql(),
            'no_eta' => '(shipments.eta is null)',
            'counted' => '(led.led_reason = 0)',
            'excluded_data' => '(led.led_reason between 1 and 4)',
            'excluded_no_rate' => '(led.led_reason = 5)',
            'estimated' => '(led.led_estimated = 1)',
            'weight_unknown' => '(led.led_weight is null)',
            default => null,
        };
    }

    private function stats(ReportFilters $filters): object
    {
        return TransitLedger::population($filters)
            ->selectRaw('count(*) as `total`')
            ->selectRaw('coalesce(sum(case when '.InTransitChecks::delayedSql().' then 1 else 0 end), 0) as `delayed`')
            ->selectRaw('coalesce(sum(case when shipments.eta is null then 1 else 0 end), 0) as `no_eta`')
            ->first();
    }

    private function cargoLines(object $stats, Collection $rows, bool $over, ReportFilters $filters): array
    {
        $url = $this->drillUrl('in_transit', ['filters' => $this->carriedFilters($filters), 'q' => 'in_transit_delayed']);

        return [
            $this->line(__(self::LANG.'lines.shipments'), (int) $stats->total, 'in_transit'),
            [...$this->line(__(self::LANG.'lines.delayed'), (int) $stats->delayed, 'delayed'), ...($url ? ['url' => $url] : [])],
            $this->line(__(self::LANG.'lines.no_eta'), (int) $stats->no_eta, 'no_eta'),
            ['label' => __(self::LANG.'lines.weight'), 'value' => $over ? '-' : delimiter($rows->sum('weight') / 1000)],
            $over ? ['label' => __(self::LANG.'lines.weight_unknown'), 'value' => '-'] : $this->line(__(self::LANG.'lines.weight_unknown'), $rows->whereNull('weight')->count(), 'weight_unknown'),
        ];
    }

    private function valueLines(Collection $rows): array
    {
        $names = $this->currencyNames();
        $counted = $rows->where('reason', 0);
        $total = $counted->sum('eur');
        $lines = [['label' => __(self::LANG.'lines.eur_total'), 'value' => delimiter($total)]];

        foreach ($counted->groupBy('currency_id')->sortKeys() as $currency => $group) {
            $lines[] = ['label' => $names[$currency] ?? '-', 'value' => delimiter($group->sum('value'))];
        }

        foreach ($rows->where('reason', 5)->groupBy('currency_id')->sortKeys() as $currency => $group) {
            $lines[] = ['label' => __(self::LANG.'lines.excluded_currency', ['currency' => $names[$currency] ?? '-']), 'value' => delimiter($group->sum('value'))];
        }

        $estimatedEur = $counted->where('estimated', true)->sum('eur');

        return [
            ...$lines,
            $this->line(__(self::LANG.'lines.counted'), $counted->count(), 'counted'),
            $this->line(__(self::LANG.'lines.excluded_data'), $rows->whereBetween('reason', [1, 4])->count(), 'excluded_data'),
            $this->line(__(self::LANG.'lines.excluded_no_rate'), $rows->where('reason', 5)->count(), 'excluded_no_rate'),
            $this->line(__(self::LANG.'lines.estimated'), $rows->where('estimated', true)->count(), 'estimated'),
            ['label' => __(self::LANG.'lines.estimated_share'), 'value' => $total > 0 ? $this->percent($estimatedEur / $total * 100) : '-'],
        ];
    }

    private function coverLines(Collection $rows, ReportFilters $filters): array
    {
        $counted = $rows->where('reason', 0);
        $total = $counted->sum('eur');
        $url = $this->drillUrl('fx_commitments', ['filters' => ['commitment_state' => 'overdue']]);
        $lines = [
            ['label' => __(self::LANG.'lines.supplied_percent'), 'value' => $total > 0 ? $this->percent($counted->where('supply', 0)->sum('eur') / $total * 100) : '-'],
            ['label' => __(self::LANG.'lines.exposure'), 'value' => delimiter($counted->whereNotNull('exposure')->sum('exposure'))],
        ];

        foreach (TransitLedger::SUPPLY as $index => $name) {
            $line = $this->line(__(self::LANG."lines.supply_{$name}", ['percent' => $filters->threshold('supply_full')]), $rows->where('supply', $index)->count(), "supply_{$name}");
            $lines[] = $name === 'unknown' && $url ? [...$line, 'url' => $url] : $line;
        }

        return $lines;
    }

    private function supplierLines(Collection $rows, ReportFilters $filters): array
    {
        $counted = $rows->where('reason', 0);
        $total = $counted->sum('eur');
        $groups = $counted->whereNotNull('seller_id')->groupBy('seller_id')
            ->map(fn (Collection $group, int $id): array => ['id' => $id, 'eur' => $group->sum('eur'), 'count' => $group->count()])
            ->sortBy([['eur', 'desc'], ['id', 'asc']])
            ->keyBy('id');
        $top = $groups->take($filters->threshold('top_n'));
        $names = $this->names('companies', $top->keys()->all());
        $lines = $top->map(fn (array $group, int $id): array => $this->supplierLine($names[$id] ?? '-', $group['eur'], $group['count'], $total))->values()->all();
        $others = $groups->slice($filters->threshold('top_n'));

        if ($others->isNotEmpty()) {
            $lines[] = $this->supplierLine(__(self::LANG.'lines.others'), $others->sum('eur'), $others->sum('count'), $total);
        }

        $none = $counted->whereNull('seller_id');

        if ($none->isNotEmpty()) {
            $lines[] = $this->supplierLine(__(self::LANG.'lines.no_seller'), $none->sum('eur'), $none->count(), $total);
        }

        return $lines ?: [['label' => __(self::LANG.'lines.none'), 'value' => '-']];
    }

    private function supplierLine(string $name, float $eur, int $count, float $total): array
    {
        return [
            'label' => __(self::LANG.'lines.supplier', ['name' => $name, 'share' => $total > 0 ? $this->percent($eur / $total * 100) : '-', 'count' => $count]),
            'value' => delimiter($eur),
        ];
    }

    private function overLines(): array
    {
        return [['label' => __(self::LANG.'lines.over_cap', ['max' => TransitLedger::$cap]), 'value' => '-']];
    }

    private function carriedFilters(ReportFilters $filters): array
    {
        return collect(array_intersect_key($filters->toArray(), array_flip(self::R4_FILTERS)))
            ->reject(fn (mixed $value, string $key): bool => $value === null || $value === [] || $value === '' || ($value === false && $key !== 'include_undated'))
            ->all();
    }

    private function counted(ReportFilters $filters): Collection
    {
        return collect(TransitLedger::rows($filters)['rows'])->where('reason', 0);
    }

    private function bar(object $line, array $roots): string
    {
        return match (true) {
            (bool) ($line->no_product ?? false) => 'no_product',
            ! isset($roots[$line->category_id]) => 'no_category',
            default => 'r'.$roots[$line->category_id],
        };
    }

    private function barChart(array $bars): array
    {
        $roots = $this->names('categories', collect(array_keys($bars))->filter(fn (string $key): bool => str_starts_with($key, 'r'))->map(fn (string $key): int => (int) substr($key, 1))->all());
        $fixed = ['no_category', 'no_product', 'charges'];
        $categories = collect($bars)->except($fixed)->sortByDesc(fn (array $stacks): float => array_sum($stacks))->keys();
        $order = [...$categories->all(), ...array_filter($fixed, fn (string $key): bool => isset($bars[$key]))];
        $labels = array_map(fn (string $key): string => in_array($key, $fixed, true) ? __(self::LANG."charts.bars.{$key}") : ($roots[(int) substr($key, 1)] ?? '-'), $order);

        return [
            'labels' => $labels,
            'datasets' => array_map(fn (int $stack, string $name): array => [
                'label' => __(self::LANG."charts.stacks.{$name}"),
                'data' => array_map(fn (string $key): float => round($bars[$key][$stack], 2), $order),
            ], [0, 1, 2, 3], ['full', 'partial', 'none', 'unknown']),
        ];
    }

    private function invoiceLines(array $ids, bool $grouped): Collection
    {
        $lines = collect();

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $query = DB::table('shipment_items')
                ->leftJoin('products as p', 'p.id', '=', 'shipment_items.product_id')
                ->whereIn('shipment_items.shipment_id', $chunk);

            $lines = $lines->concat($grouped ? $this->groupedInvoice($query) : $this->plainInvoice($query));
        }

        return $lines;
    }

    private function groupedInvoice(QueryBuilder $query): Collection
    {
        $noProduct = '(case when shipment_items.product_id is null then 1 else 0 end)';

        return $query
            ->selectRaw("shipment_items.shipment_id, p.category_id as category_id, {$noProduct} as no_product, SUM(shipment_items.total_amount) as amount")
            ->groupBy('shipment_items.shipment_id', 'p.category_id')
            ->groupByRaw($noProduct)
            ->get();
    }

    private function plainInvoice(QueryBuilder $query): Collection
    {
        $column = self::nameColumn();

        return $query
            ->selectRaw("shipment_items.shipment_id, shipment_items.product_id, COALESCE(shipment_items.{$column}, shipment_items.name, shipment_items.english_name) as label, p.category_id as category_id, shipment_items.unit, shipment_items.quantity, COALESCE(shipment_items.total_amount, 0) as amount, shipment_items.net_weight, shipment_items.gross_weight")
            ->get();
    }

    private function orderLines(array $orderIds, bool $grouped): Collection
    {
        $lines = collect();
        $amount = 'COALESCE(i.line_total, i.quantity * i.unit_price)';

        foreach (array_chunk($orderIds, self::CHUNK) as $chunk) {
            $query = DB::table('registered_order_items as i')
                ->leftJoin('products as p', 'p.id', '=', 'i.product_id')
                ->whereNull('i.deleted_at')
                ->whereIn('i.registered_order_id', $chunk);

            $lines = $lines->concat($grouped
                ? $query->selectRaw("i.registered_order_id, p.category_id as category_id, 0 as no_product, SUM({$amount}) as amount")->groupBy('i.registered_order_id', 'p.category_id')->get()
                : $query->selectRaw("i.registered_order_id, i.product_id, null as label, p.category_id as category_id, i.unit, i.quantity, COALESCE({$amount}, 0) as amount, i.net_weight, i.gross_weight")->get());
        }

        return $lines;
    }

    private function rootMap(): array
    {
        return DB::table('category_closure as cc')
            ->join('categories as r', fn (JoinClause $join) => $join->on('r.id', '=', 'cc.ancestor_id')->whereNull('r.parent_id'))
            ->pluck('cc.ancestor_id', 'cc.descendant_id')
            ->all();
    }

    private function categoryPaths(): Collection
    {
        $column = self::nameColumn();

        return DB::table('categories')->get(['id', 'parent_id', 'name', 'english_name'])->keyBy('id')->map(fn (object $category): array => [
            'parent_id' => $category->parent_id,
            'name' => (string) ($category->{$column} ?? $category->name ?? $category->english_name),
        ]);
    }

    private function pathOf(mixed $categoryId, Collection $categories): string
    {
        $names = [];

        for ($node = $categories->get($categoryId); $node && count($names) < 6; $node = $categories->get($node['parent_id'])) {
            array_unshift($names, $node['name']);
        }

        return implode(' » ', $names);
    }

    private function names(string $table, ?array $ids = null): array
    {
        $column = self::nameColumn();
        $other = $column === 'name' ? 'english_name' : 'name';

        return DB::table($table)
            ->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
            ->get(['id', 'name', 'english_name'])
            ->mapWithKeys(fn (object $row): array => [(int) $row->id => (string) ($row->{$column} ?: $row->{$other})])
            ->all();
    }

    private function shipmentExportRow(Shipment $shipment): array
    {
        $names = $this->currencyNames();
        $orderCurrency = $names[$shipment->registeredOrder?->currency_id] ?? '';

        return [
            'shipment_no' => $shipment->shipment_no,
            'part' => $shipment->part,
            'registered_order' => $shipment->registeredOrder?->ro_number,
            'seller' => $shipment->registeredOrder?->sellerCompany?->getLocalizedNameAttribute(),
            'carrier' => $shipment->carrier?->getLocalizedNameAttribute(),
            'products' => $shipment->products_text,
            'value' => $shipment->led_value !== null ? delimiter($shipment->led_value, $names[$shipment->led_currency_id] ?? null) : null,
            'eur' => $shipment->led_eur !== null ? delimiter($shipment->led_eur) : null,
            'source' => $this->sourceLabel($shipment),
            'supply' => $this->supplyLabel($shipment->led_supply !== null ? (int) $shipment->led_supply : null),
            'supplied_percent' => $shipment->led_ratio !== null ? $this->percent((float) $shipment->led_ratio * 100) : null,
            'supplied_remaining' => $shipment->led_supplied !== null ? delimiter($shipment->led_supplied).' / '.delimiter(max((float) $shipment->led_order_value - (float) $shipment->led_supplied, 0), $orderCurrency) : null,
            'weight' => $shipment->led_weight !== null ? delimiter($shipment->led_weight) : null,
            'etd' => $shipment->etd ? adaptiveDate($shipment->etd) : null,
            'eta' => $shipment->eta ? adaptiveDate($shipment->eta) : null,
            'days_to_eta' => $shipment->days_to_eta,
            'tracking_status' => $shipment->trackingStatus?->getLocalizedNameAttribute(),
            'flags' => $this->flagsText((int) $shipment->led_flags),
        ];
    }

    private function productExportRow(array $line): array
    {
        return [
            'category' => $line['category'],
            'product' => $line['product'],
            'quantity' => $line['quantity'] === null ? null : trim(delimiter($line['quantity']).' '.$line['unit']),
            'net_weight' => $line['net_weight'] === null ? null : delimiter($line['net_weight']),
            'gross_weight' => $line['gross_weight'] === null ? null : delimiter($line['gross_weight']),
            'eur' => delimiter($line['eur']),
            'shipments' => $line['shipments'],
            'eta' => $line['eta'] ? adaptiveDate($line['eta']) : null,
            'supply' => $line['supply'] === null ? null : $this->supplyLabel($line['supply']),
        ];
    }

    private function percent(float $value): string
    {
        return number_format($value, 1, '.', '').'%';
    }

    private static function nameColumn(): string
    {
        return app()->getLocale() === 'fa' ? 'name' : 'english_name';
    }

    private function card(string $key, array $lines): array
    {
        return ['key' => $key, 'label' => __(self::LANG."cards.{$key}"), 'lines' => $lines, 'tone' => null];
    }

    private function line(string $label, int $value, string $quick): array
    {
        return ['label' => $label, 'value' => (string) $value, 'quick' => $quick];
    }
}
