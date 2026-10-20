<?php

namespace App\Services\Reports\Builders;

use App\Models\Correspondence;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use App\Services\Reports\Checks\CorrespondenceChecks;
use App\Services\Reports\Checks\RegisteredOrderChecks;
use App\Services\Reports\Support\FileActivity;
use App\Services\Reports\Support\FileRowLoader;
use App\Services\Reports\Support\ReportFilters;
use App\Services\SmartCacheManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationsReportService implements ReportService
{
    public const STAGES = [
        'registration' => 'resources/registeredOrder/strings.general.model_label',
        'bank_profile' => 'resources/bankProfile/strings.general.model_label',
        'payment' => 'resources/payment/strings.general.model_label',
        'shipment' => 'resources/shipment/strings.general.model_label',
        'customs' => 'resources/custom/strings.general.model_label',
    ];

    private const LANG = 'resources/report/strings.operations.';

    private const EXPORT_COLUMNS = [
        'ro_number' => 'resources/registeredOrder/strings.table.ro_number',
        'contract_no' => 'resources/registeredOrder/strings.table.contract_number',
        'seller' => 'resources/registeredOrder/strings.table.seller',
        'buyer' => 'resources/registeredOrder/strings.table.buyer',
        'currency' => 'resources/registeredOrder/strings.form.currency',
        'products' => self::LANG.'columns.products',
        'stage' => self::LANG.'columns.stage',
        'status' => 'resources/registeredOrder/strings.table.status',
        'order_date' => 'resources/registeredOrder/strings.table.order_date',
        'validity_date' => 'resources/registeredOrder/strings.form.validity_date',
        'purchase_orders_count' => 'resources/registeredOrder/strings.table.purchase_orders_count',
        'fx_status' => self::LANG.'columns.fx_status',
        'shipment_status' => self::LANG.'columns.shipment_status',
        'operation_status' => self::LANG.'columns.operation_status',
        'doc_status' => self::LANG.'columns.doc_status',
        'clearance_status' => self::LANG.'columns.clearance_status',
        'last_operation' => self::LANG.'columns.last_operation',
        'last_activity' => self::LANG.'columns.last_activity',
        'last_by' => self::LANG.'columns.last_by',
        'idle_days' => self::LANG.'columns.idle_days',
        'last_correspondence' => self::LANG.'columns.last_correspondence',
    ];

    public function __construct(private readonly FileRowLoader $loader) {}

    public function query(ReportFilters $filters): Builder
    {
        [$stage, $bindings] = $this->stageSql();

        $query = FileActivity::join(RegisteredOrderChecks::openOrders($filters))
            ->select('registered_orders.*')
            ->selectRaw(FileActivity::idleSql().' as idle_days')
            ->selectRaw('file_activity.last_at as last_activity_at, file_activity.last_ref as last_ref')
            ->selectRaw('file_activity.last_at is null as idle_from_updated_at')
            ->selectRaw("{$stage} as stage", $bindings)
            ->withCount('purchaseOrders')
            ->with(['sellerCompany', 'buyerCompany', 'currency', 'status', 'items.product'])
            ->afterQuery(fn (Collection $orders): Collection => $this->loader->load($orders));

        return $this->applyQuick($query, $filters->quick, $filters);
    }

    private function applyQuick(Builder $query, ?string $quick, ReportFilters $filters): Builder
    {
        if (str_starts_with((string) $quick, 'stage_')) {
            [$stage, $bindings] = $this->stageSql();

            return in_array(substr($quick, 6), array_keys(self::STAGES), true)
                ? $query->whereRaw("({$stage}) = ?", [...$bindings, substr($quick, 6)])
                : $query;
        }

        return match ($quick) {
            'idle_threshold' => $query->whereRaw(FileActivity::idleSql().' > ?', [$filters->threshold('idle')]),
            'idle_over_14' => $query->whereRaw(FileActivity::idleSql().' > ?', [$filters->threshold('idle_error')]),
            'expiring', 'expired' => $query->whereRaw(...$this->validityRule($quick, $filters)),
            'no_validity' => $query->whereNull('registered_orders.validity_date'),
            default => $query,
        };
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function validityRule(string $rule, ReportFilters $filters): array
    {
        return $rule === 'expiring'
            ? [RegisteredOrderChecks::UNFULFILLED_SQL.' and registered_orders.validity_date between ? and ?', [today()->toDateString(), today()->addDays($filters->threshold('validity'))->toDateString()]]
            : [RegisteredOrderChecks::UNFULFILLED_SQL.' and registered_orders.validity_date < ?', [today()->toDateString()]];
    }

    public function summary(ReportFilters $filters): array
    {
        return $this->cached('summary', $filters, fn (): array => $this->buildSummary($filters));
    }

    private function cached(string $kind, ReportFilters $filters, callable $callback): array
    {
        return SmartCacheManager::remember('ReportCheck', ['kind' => "operations_{$kind}", 'filters' => $filters->cacheKey(), 'locale' => app()->getLocale()], 5, $callback);
    }

    private function buildSummary(ReportFilters $filters): array
    {
        $idle = $this->idleCounts($filters);
        $validity = $this->validityCounts($filters);
        $priorities = $this->priorityCounts($filters);

        return [
            $this->card('stages', [...$this->stageLines($filters)]),
            $this->card('idle', [
                $this->line('idle_warning', (string) $idle->over_threshold, ['days' => $filters->threshold('idle')], 'idle_threshold'),
                $this->line('idle_critical', (string) $idle->over_error, ['days' => $filters->threshold('idle_error')], 'idle_over_14'),
            ]),
            $this->card('expiring', [
                $this->line('expiring', (string) $validity->expiring, ['days' => $filters->threshold('validity')], 'expiring'),
                $this->line('expired', (string) $validity->expired, quick: 'expired'),
                $this->line('no_validity', (string) $validity->undated, quick: 'no_validity'),
            ]),
            $this->card('correspondence', [
                $this->line('high', (string) ($priorities['high'] ?? 0)),
                $this->line('urgent', (string) ($priorities['urgent'] ?? 0)),
            ]),
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        return $this->query($filters)->orderBy('registered_orders.id')->lazy()->map(fn (RegisteredOrder $order): array => $this->row($order));
    }

    public function exportLabels(): array
    {
        return array_map(fn (string $key): string => __($key), self::EXPORT_COLUMNS);
    }

    /**
     * @return array{labels: array<int, string>, counts: array<int, int>}
     */
    public function chartStages(ReportFilters $filters): array
    {
        return $this->cached('chart_stages', $filters, fn (): array => $this->buildChartStages($filters));
    }

    private function buildChartStages(ReportFilters $filters): array
    {
        $counts = $this->stageCounts($filters);

        return [
            'labels' => array_map(fn (string $key): string => __($key), array_values(self::STAGES)),
            'counts' => array_map(fn (string $stage): int => (int) ($counts[$stage] ?? 0), array_keys(self::STAGES)),
        ];
    }

    /**
     * @return array{labels: array<int, string>, active: array<int, int>, idle: array<int, int>}
     */
    public function chartIdleByStage(ReportFilters $filters): array
    {
        return $this->cached('chart_idle_by_stage', $filters, fn (): array => $this->buildChartIdleByStage($filters));
    }

    private function buildChartIdleByStage(ReportFilters $filters): array
    {
        [$stage, $bindings] = $this->stageSql();
        $orders = FileActivity::join(RegisteredOrderChecks::openOrders($filters))
            ->select('registered_orders.id')
            ->selectRaw("{$stage} as stage", $bindings)
            ->selectRaw(FileActivity::idleSql().' > ? as idle', [$filters->threshold('idle')]);
        $counts = DB::query()->fromSub($orders, 'o')->selectRaw('o.stage, o.idle, count(*) as total')->groupBy('o.stage', 'o.idle')->get();
        $total = fn (string $stage, int $idle): int => (int) $counts->where('stage', $stage)->where('idle', $idle)->sum('total');

        return [
            'labels' => array_map(fn (string $key): string => __($key), array_values(self::STAGES)),
            'active' => array_map(fn (string $stage): int => $total($stage, 0), array_keys(self::STAGES)),
            'idle' => array_map(fn (string $stage): int => $total($stage, 1), array_keys(self::STAGES)),
        ];
    }

    public static function sellerNameSql(): string
    {
        $column = app()->getLocale() === 'fa' ? 'name' : 'english_name';

        return "(select companies.{$column} from companies where companies.id = registered_orders.seller_id)";
    }

    public function stageOrderSql(): string
    {
        return "FIELD(stage, '".implode("','", array_keys(self::STAGES))."')";
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    public function paidSql(): array
    {
        $paid = $this->exists(DB::table('payments as p')->whereNull('p.deleted_at')->where('p.targetable_type', RegisteredOrder::class)->whereColumn('p.targetable_id', 'registered_orders.id'));
        $paidViaOrder = $this->exists(DB::table('payments as p')
            ->join('registered_order_purchase_order as link', 'link.purchase_order_id', '=', 'p.targetable_id')
            ->whereNull('p.deleted_at')
            ->where('p.targetable_type', PurchaseOrder::class)
            ->whereColumn('link.registered_order_id', 'registered_orders.id')
            ->whereRaw('(select count(*) from registered_order_purchase_order x where x.purchase_order_id = p.targetable_id) = 1'));

        return [$paid[0].' or '.$paidViaOrder[0], [...$paid[1], ...$paidViaOrder[1]]];
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    public function stageSql(): array
    {
        $parts = [
            'customs' => $this->exists($this->live('customs')),
            'shipment' => $this->exists($this->live('shipments')),
            'payment' => $this->paidSql(),
            'bank_profile' => $this->exists($this->live('bank_profiles')),
        ];
        $sql = 'case';
        $bindings = [];

        foreach ($parts as $stage => [$condition, $parameters]) {
            $sql .= " when {$condition} then '{$stage}'";
            $bindings = [...$bindings, ...$parameters];
        }

        return [$sql." else 'registration' end", $bindings];
    }

    private function live(string $table): QueryBuilder
    {
        return DB::table($table)->whereNull("{$table}.deleted_at")->whereColumn("{$table}.registered_order_id", 'registered_orders.id');
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function exists(QueryBuilder $query): array
    {
        $probe = $query->selectRaw('1');

        return ['exists ('.$probe->toSql().')', $probe->getBindings()];
    }

    private function stageCounts(ReportFilters $filters): Collection
    {
        [$stage, $bindings] = $this->stageSql();
        $orders = RegisteredOrderChecks::openOrders($filters)->select('registered_orders.id')->selectRaw("{$stage} as stage", $bindings);

        return DB::query()->fromSub($orders, 'o')->selectRaw('o.stage, count(*) as total')->groupBy('o.stage')->pluck('total', 'stage');
    }

    private function stageLines(ReportFilters $filters): array
    {
        $counts = $this->stageCounts($filters);
        $lines = [['label' => __(self::LANG.'lines.total'), 'value' => (string) $counts->sum(), 'quick' => 'all_open']];

        foreach (self::STAGES as $stage => $label) {
            $lines[] = ['label' => __($label), 'value' => (string) ($counts[$stage] ?? 0), 'quick' => "stage_{$stage}"];
        }

        return $filters->showClosed ? $lines : [...$lines, ['label' => __(self::LANG.'lines.closed_hidden'), 'value' => (string) RegisteredOrderChecks::closedCount($filters)]];
    }

    private function idleCounts(ReportFilters $filters): object
    {
        return FileActivity::join(RegisteredOrderChecks::openOrders($filters))
            ->selectRaw('coalesce(sum('.FileActivity::idleSql().' > ?), 0) as over_threshold', [$filters->threshold('idle')])
            ->selectRaw('coalesce(sum('.FileActivity::idleSql().' > ?), 0) as over_error', [$filters->threshold('idle_error')])
            ->toBase()
            ->first();
    }

    private function validityCounts(ReportFilters $filters): object
    {
        return RegisteredOrderChecks::openOrders($filters)
            ->selectRaw('coalesce(sum('.$this->validityRule('expiring', $filters)[0].'), 0) as expiring', $this->validityRule('expiring', $filters)[1])
            ->selectRaw('coalesce(sum('.$this->validityRule('expired', $filters)[0].'), 0) as expired', $this->validityRule('expired', $filters)[1])
            ->selectRaw('coalesce(sum(registered_orders.validity_date is null), 0) as undated')
            ->toBase()
            ->first();
    }

    private function priorityCounts(ReportFilters $filters): Collection
    {
        return CorrespondenceChecks::onOpenFiles(Correspondence::query(), $filters)
            ->selectRaw('correspondences.priority, count(*) as total')
            ->groupBy('correspondences.priority')
            ->pluck('total', 'priority');
    }

    private function card(string $key, array $lines): array
    {
        return ['key' => $key, 'label' => __(self::LANG."cards.{$key}"), 'lines' => $lines, 'tone' => null];
    }

    private function line(string $key, string $value, array $replace = [], ?string $quick = null): array
    {
        return ['label' => __(self::LANG."lines.{$key}", $replace), 'value' => $value] + ($quick ? ['quick' => $quick] : []);
    }

    private function row(RegisteredOrder $order): array
    {
        return [
            'ro_number' => $order->ro_number,
            'contract_no' => $order->contract_no,
            'seller' => $order->sellerCompany?->getLocalizedNameAttribute(),
            'buyer' => $order->buyerCompany?->getLocalizedNameAttribute(),
            'currency' => $order->currency?->getLocalizedNameAttribute(),
            'products' => $order->items->pluck('product')->filter()->unique('id')->map->localized_name->implode(', '),
            'stage' => __(self::STAGES[$order->stage]),
            'status' => $order->status?->localized_name,
            'order_date' => $order->order_date ? adaptiveDate($order->order_date) : null,
            'validity_date' => $order->validity_date ? adaptiveDate($order->validity_date) : null,
            'purchase_orders_count' => $order->purchase_orders_count,
            'fx_status' => $order->fx_status?->localized_name,
            'shipment_status' => $order->shipment_status?->localized_name,
            'operation_status' => $order->operation_status?->localized_name,
            'doc_status' => $order->doc_status?->localized_name,
            'clearance_status' => $order->clearance_status?->localized_name,
            'last_operation' => $order->last_event['text'] ?? null,
            'last_activity' => $order->last_activity_at ? adaptiveDate($order->last_activity_at, true) : null,
            'last_by' => $order->last_event['by'] ?? null,
            'idle_days' => $order->idle_days,
            'last_correspondence' => $order->latest_mail?->subject,
        ];
    }
}
