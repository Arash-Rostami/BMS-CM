<?php

namespace App\Services\Reports\Builders;

use App\Models\Product;
use App\Models\PurchaseRequestItem;
use App\Services\Reports\Checks\InTransitChecks;
use App\Services\Reports\Checks\RequesterChecks;
use App\Services\Reports\Rules\RequesterStages;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Support\RequesterRowLoader;
use App\Services\SmartCacheManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RequesterReportService implements ReportService
{
    private const LANG = 'resources/report/strings.requester.';

    private const FINISHED_QUICK = ['all', 'stage_cleared', 'weight_unknown'];

    private const EXPORT_COLUMNS = [
        'pr_number' => 'resources/purchaseRequest/strings.table.pr_number',
        'required_by_date' => 'resources/purchaseRequest/strings.table.required_by_date',
        'requester' => 'resources/purchaseRequest/strings.table.requester',
        'product' => 'resources/purchaseRequest/strings.form.product',
        'quantity' => self::LANG.'columns.quantity',
        'unit' => 'resources/purchaseRequest/strings.infolist.item_unit',
        'shipped_quantity' => self::LANG.'columns.shipped_quantity',
        'remaining_quantity' => self::LANG.'columns.remaining_quantity',
        'gross_weight' => self::LANG.'columns.gross_weight',
        'net_weight' => self::LANG.'columns.net_weight',
        'shipments' => self::LANG.'columns.shipments',
        'stage' => self::LANG.'columns.stage',
        'eta' => self::LANG.'columns.eta',
        'latest_status' => self::LANG.'columns.latest_status',
        'pr_status' => self::LANG.'columns.pr_status',
        'notes' => self::LANG.'columns.notes',
    ];

    public function __construct(private readonly RequesterRowLoader $loader) {}

    public function base(ReportFilters $filters): Builder
    {
        $items = PurchaseRequestItem::query()
            ->join('purchase_requests as pr', fn ($join) => $join->on('pr.id', '=', 'purchase_request_items.purchase_request_id')->whereNull('pr.deleted_at'));

        return RequesterChecks::narrowRequests($items, $filters, 'pr')
            ->when($filters->productId, fn (Builder $items, int $id): Builder => $items->where('purchase_request_items.product_id', $id))
            ->when($filters->categoryId, fn (Builder $items): Builder => $items->whereIn('purchase_request_items.product_id', Product::query()->whereIn('category_id', $filters->categoryIds())->select('id')))
            ->when($filters->registeredOrderId, fn (Builder $items, int $id): Builder => $items->whereRaw('exists (select 1 from registered_order_purchase_request f where f.purchase_request_id = purchase_request_items.purchase_request_id and f.registered_order_id = ?)', [$id]))
            ->when(in_array($filters->stage, RequesterStages::STAGES, true), fn (Builder $items): Builder => $items->whereRaw('('.RequesterStages::stageSql($filters).') = ?', [$filters->stage]));
    }

    public function query(ReportFilters $filters): Builder
    {
        $query = $this->base($filters)
            ->leftJoin('users as u', 'u.id', '=', 'pr.requester_id')
            ->select('purchase_request_items.id', 'purchase_request_items.purchase_request_id', 'purchase_request_items.product_id', 'purchase_request_items.quantity', 'purchase_request_items.unit', 'pr.pr_number', 'pr.required_by_date', 'pr.status_id as pr_status_id', 'u.name as requester_name')
            ->selectRaw('('.RequesterStages::stageSql($filters).') as stage')
            ->with('product:id,name,english_name')
            ->afterQuery(fn (Collection $items): Collection => $this->loader->load($items, $filters));

        return $this->applyQuick($query, $filters);
    }

    private function applyQuick(Builder $query, ReportFilters $filters): Builder
    {
        $quick = $filters->quick;
        $stage = str_starts_with((string) $quick, 'stage_') ? substr($quick, 6) : null;
        $flags = RequesterStages::flags($filters);
        $query = match (true) {
            in_array($stage, RequesterStages::STAGES, true) => $query->whereRaw('('.RequesterStages::stageSql($filters).') = ?', [$stage]),
            isset($flags[$quick]) => $query->whereRaw($flags[$quick]),
            default => $query,
        };

        return $this->showsFinished($filters) || in_array($quick, self::FINISHED_QUICK, true)
            ? $query
            : $query->whereRaw('('.RequesterStages::stageSql($filters).") <> 'cleared'");
    }

    public function summary(ReportFilters $filters): array
    {
        $counts = $this->counts($filters);
        $weight = $this->weightInTransit($filters);
        $stage = fn (string $key): array => $this->line(RequesterStages::label($key), $this->total($counts, $key), "stage_{$key}");

        return [
            $this->card('not_shipped', [
                $this->line(__(self::LANG.'lines.total'), (string) $counts->sum('total'), 'all'),
                $stage('reviewing'),
                $stage('sourcing'),
                $stage('awaiting'),
            ]),
            $this->card('on_the_way', [
                $stage('in_transit'),
                $stage('arrived'),
                $this->line(__(self::LANG.'lines.arriving', ['days' => $filters->threshold('arrive_1')]), (string) $counts->sum('arriving'), 'arriving'),
                $this->line(__(self::LANG.'lines.no_eta'), (string) $counts->sum('no_eta'), 'no_eta'),
            ]),
            $this->card('customs', [$stage('at_customs'), $stage('cleared'), ...$this->hiddenLine($counts, $filters)]),
            $this->card('late_weight', [
                $this->line($this->lateLabel($filters), (string) $counts->sum('late'), 'late'),
                $this->line(__(self::LANG.'lines.weight_unknown'), (string) $counts->sum('weight_unknown'), 'weight_unknown'),
                $this->line(__(self::LANG.'lines.weight_unknown_unshipped'), (string) $counts->sum('weight_unknown_unshipped'), 'weight_unknown_unshipped'),
                ['label' => __(self::LANG.'lines.net_weight'), 'value' => delimiter($weight->net ?? 0)],
                ['label' => __(self::LANG.'lines.gross_weight'), 'value' => delimiter($weight->gross ?? 0)],
            ]),
        ];
    }

    private function lateLabel(ReportFilters $filters): string
    {
        $grace = $filters->threshold('late_grace');

        return $grace > 0 ? __(self::LANG.'lines.late_grace', ['days' => $grace]) : __(self::LANG.'lines.late');
    }

    private function hiddenLine(Collection $counts, ReportFilters $filters): array
    {
        return $this->showsFinished($filters) ? [] : [['label' => __(self::LANG.'lines.finished_hidden'), 'value' => $this->total($counts, 'cleared')]];
    }

    private function showsFinished(ReportFilters $filters): bool
    {
        return $filters->showClosed || $filters->stage === 'cleared';
    }

    public function rows(ReportFilters $filters): iterable
    {
        return $this->query($filters)
            ->orderBy('pr.required_by_date')
            ->orderBy('purchase_request_items.id')
            ->lazy()
            ->map(fn (PurchaseRequestItem $item): array => $this->row($item));
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
        $counts = $this->counts($filters);

        return [
            'labels' => array_map(fn (string $stage): string => RequesterStages::label($stage), RequesterStages::STAGES),
            'counts' => array_map(fn (string $stage): int => (int) ($counts[$stage]->total ?? 0), RequesterStages::STAGES),
        ];
    }

    /**
     * @return array{labels: array<int, string>, on_time: array<int, int>, late: array<int, int>}
     */
    public function chartLateByStage(ReportFilters $filters): array
    {
        $counts = $this->counts($filters);
        $late = fn (string $stage): int => (int) ($counts[$stage]->late ?? 0);

        return [
            'labels' => array_map(fn (string $stage): string => RequesterStages::label($stage), RequesterStages::STAGES),
            'on_time' => array_map(fn (string $stage): int => (int) ($counts[$stage]->total ?? 0) - $late($stage), RequesterStages::STAGES),
            'late' => array_map($late, RequesterStages::STAGES),
        ];
    }

    private function counts(ReportFilters $filters): Collection
    {
        $rows = SmartCacheManager::remember('ReportCheck', ['kind' => 'requester_counts', 'filters' => $filters->cacheKey()], 5, fn (): array => $this->loadCounts($filters));

        return collect($rows)->map(fn (array $row): object => (object) $row)->keyBy('stage');
    }

    private function loadCounts(ReportFilters $filters): array
    {
        $items = $this->base($filters)->select('purchase_request_items.id')->selectRaw('('.RequesterStages::stageSql($filters).') as stage');

        foreach (RequesterStages::flags($filters) as $key => $sql) {
            $items->selectRaw("{$sql} as {$key}");
        }

        return DB::query()
            ->fromSub($items, 'c')
            ->selectRaw('c.stage, count(*) as total, coalesce(sum(c.late), 0) as late, coalesce(sum(c.arriving), 0) as arriving, coalesce(sum(c.no_eta), 0) as no_eta, coalesce(sum(c.weight_unknown), 0) as weight_unknown, coalesce(sum(c.weight_unknown_unshipped), 0) as weight_unknown_unshipped')
            ->groupBy('c.stage')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }

    private function weightInTransit(ReportFilters $filters): object
    {
        $lines = $this->base($filters)
            ->join('shipment_items as si', 'si.product_id', '=', 'purchase_request_items.product_id')
            ->join('shipments', 'shipments.id', '=', 'si.shipment_id')
            ->whereRaw(RequesterStages::matchSql($filters))
            ->whereRaw(InTransitChecks::inTransitSql($filters))
            ->select('si.id');

        return DB::table('shipment_items')->whereIn('id', $lines)->selectRaw('sum(net_weight) as net, sum(gross_weight) as gross')->first();
    }

    private function total(Collection $counts, string $stage): string
    {
        return (string) (int) ($counts[$stage]->total ?? 0);
    }

    private function card(string $key, array $lines): array
    {
        return ['key' => $key, 'label' => __(self::LANG."cards.{$key}"), 'lines' => $lines, 'tone' => null];
    }

    private function line(string $label, string $value, string $quick): array
    {
        return ['label' => $label, 'value' => $value, 'quick' => $quick];
    }

    private function row(PurchaseRequestItem $item): array
    {
        return [
            'pr_number' => $item->pr_number,
            'required_by_date' => $item->required_by_date ? adaptiveDate($item->required_by_date) : null,
            'requester' => $item->requester_name,
            'product' => $item->product?->localized_name,
            'quantity' => $item->quantity,
            'unit' => $item->unit,
            'shipped_quantity' => $item->shipped_quantity,
            'remaining_quantity' => $item->remaining_quantity,
            'gross_weight' => $item->gross_weight,
            'net_weight' => $item->net_weight,
            'shipments' => implode(', ', $item->shipment_labels),
            'stage' => RequesterStages::label($item->stage),
            'eta' => $item->eta_missing ? __(self::LANG.'columns.no_eta') : ($item->latest_eta ? adaptiveDate($item->latest_eta) : null),
            'latest_status' => $item->latest_status?->localized_name,
            'pr_status' => $item->pr_status?->localized_name,
            'notes' => $this->notes($item),
        ];
    }

    private function notes(PurchaseRequestItem $item): string
    {
        return collect([
            $item->chain_unspecific ? __(self::LANG.'notes.chain_unspecific') : null,
            $item->unit_mismatch ? __(self::LANG.'notes.unit_mismatch') : null,
        ])->filter()->implode(' ');
    }
}
