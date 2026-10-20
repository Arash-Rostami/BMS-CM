<?php

namespace App\Services\Reports\Builders;

use App\Filament\Resources\Operational\CustomResource\Enums\ClearanceStatus;
use App\Filament\Resources\Operational\CustomResource\Enums\GuaranteeStatus;
use App\Models\Custom;
use App\Models\Status;
use App\Services\Calendar\Display\CalendarRange;
use App\Services\Reports\Checks\CustomChecks;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Support\SqlToday;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomsReportService implements ReportService
{
    private const LANG = 'resources/report/strings.customs.';

    private const PRODUCTS_MAX_BYTES = 65535;

    private const GUARANTEE_QUICK = ['guarantees_not_returned', 'guarantees_cleared'];

    private const EXPORT_COLUMNS = [
        'custom_no' => 'resources/custom/strings.form.custom_no',
        'declaration_no' => 'resources/custom/strings.form.declaration_no',
        'contract_no' => 'resources/custom/strings.form.contract_no',
        'shipment_no' => 'resources/custom/strings.table.shipment',
        'registered_order' => 'resources/custom/strings.form.registered_order',
        'seller' => 'resources/report/strings.filters.seller',
        'products' => self::LANG.'columns.products',
        'weight' => self::LANG.'columns.weight',
        'clearance_type' => 'resources/custom/strings.form.clearance_type',
        'clearance_status' => 'resources/custom/strings.form.clearance_status',
        'warehouse_date' => 'resources/shipment/strings.form.warehouse_date',
        'eta' => 'resources/shipment/strings.form.eta',
        'doc_submission_date' => 'resources/custom/strings.form.doc_submission_date',
        'days_in_customs' => self::LANG.'columns.days_in_customs',
        'clearance_date' => 'resources/custom/strings.form.clearance_date',
        'customs_quantity' => 'resources/shipment/strings.form.customs_quantity',
        'ten_percent_exit_date' => 'resources/custom/strings.form.ten_percent_exit_date',
        'bank_guarantee_status' => 'resources/custom/strings.form.bank_guarantee_status',
        'commitment_status' => 'resources/custom/strings.form.commitment_status',
        'doc_status' => 'resources/shipment/strings.table.doc_status',
    ];

    public const PRODUCTS_SQL = 'COALESCE(si.products, roi.products)';

    public const WEIGHT_SQL = 'COALESCE(si.weight, roi.weight)';

    public function query(ReportFilters $filters): Builder
    {
        $basis = CustomChecks::basisExpression($filters);
        DB::statement('SET SESSION group_concat_max_len = '.self::PRODUCTS_MAX_BYTES);

        $scoped = $this->joinShipment(CustomChecks::scoped(Custom::query(), $filters));
        $shipmentIds = CustomChecks::scoped(Custom::query(), $filters)->select('customs.shipment_id');
        $orderIds = CustomChecks::scoped(Custom::query(), $filters)->select('customs.registered_order_id');

        return $this->applyQuick($scoped, $filters->quick, $filters)
            ->select('customs.*')
            ->selectRaw('DATEDIFF(COALESCE(customs.clearance_date, '.SqlToday::literal()."), {$basis}) as days_in_customs")
            ->selectRaw("({$basis}) is null as basis_missing")
            ->selectRaw(self::PRODUCTS_SQL.' as products_text')
            ->selectRaw(self::WEIGHT_SQL.' as total_weight')
            ->selectRaw("(CASE WHEN si.shipment_id IS NOT NULL THEN 'invoice' WHEN roi.registered_order_id IS NOT NULL THEN 'order' ELSE NULL END) as weight_source")
            ->leftJoinSub($this->shipmentItems()->whereIn('shipment_items.shipment_id', $shipmentIds), 'si', fn (JoinClause $join) => $join->on('si.shipment_id', '=', 'customs.shipment_id'))
            ->leftJoinSub($this->orderItems()->whereIn('registered_order_items.registered_order_id', $orderIds), 'roi', fn (JoinClause $join) => $join->on('roi.registered_order_id', '=', 'customs.registered_order_id'))
            ->with(['shipment.docStatus', 'registeredOrder.sellerCompany:id,name,english_name', 'clearanceStatus', 'bankGuaranteeStatus', 'commitmentStatus']);
    }

    public function summary(ReportFilters $filters): array
    {
        $stats = $this->openStats($filters);

        return [
            $this->openCard($stats),
            $this->stageCard($filters),
            $this->delaysCard($stats),
            $this->guaranteesCard($filters),
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        return $this->query($filters)->orderBy('customs.id')->lazy()->map(fn (Custom $custom): array => $this->row($custom));
    }

    public function exportLabels(): array
    {
        return array_map(fn (string $key): string => __($key), self::EXPORT_COLUMNS);
    }

    /**
     * @return array{labels: array<int, string>, counts: array<int, int>}
     */
    public function chartStage(ReportFilters $filters): array
    {
        $counts = $this->stageCounts($this->visible(Custom::query(), $filters));
        $stages = $this->orderedStages();

        return [
            'labels' => $stages->map(fn (Status $status): string => (string) $status->getLocalizedNameAttribute())->all(),
            'counts' => $stages->map(fn (Status $status): int => (int) ($counts[$status->id] ?? 0))->all(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, averages: array<int, float>}
     */
    public function chartByMonth(ReportFilters $filters): array
    {
        $basis = CustomChecks::basisExpression($filters);
        $rows = $this->joinShipment(CustomChecks::scoped(Custom::query(), $filters))
            ->whereNotNull('customs.clearance_date')
            ->whereRaw("({$basis}) is not null")
            ->selectRaw("customs.clearance_date as cleared_at, DATEDIFF(customs.clearance_date, {$basis}) as days")
            ->orderBy('customs.clearance_date')
            ->get();

        $buckets = [];
        $labels = [];
        foreach ($rows as $row) {
            $key = $this->monthKey((string) $row->cleared_at);
            $buckets[$key][] = (int) $row->days;
            $labels[$key] ??= CalendarRange::forMonth(substr((string) $row->cleared_at, 0, 10), isJalaliCalendar())->label();
        }

        return [
            'labels' => array_values($labels),
            'averages' => array_values(array_map(fn (array $days): float => round(array_sum($days) / count($days), 1), $buckets)),
        ];
    }

    private function row(Custom $custom): array
    {
        return [
            'custom_no' => $custom->custom_no,
            'declaration_no' => $custom->declaration_no,
            'contract_no' => $custom->contract_no,
            'shipment_no' => $custom->shipment?->shipment_no,
            'registered_order' => $custom->registeredOrder?->ro_number,
            'seller' => $custom->registeredOrder?->sellerCompany?->getLocalizedNameAttribute(),
            'products' => $custom->products_text,
            'weight' => $custom->total_weight !== null ? delimiter($custom->total_weight) : null,
            'clearance_type' => $custom->clearance_type ? (__('resources/custom/strings.general.clearance_types')[$custom->clearance_type] ?? $custom->clearance_type) : null,
            'clearance_status' => $custom->clearanceStatus?->getLocalizedNameAttribute(),
            'warehouse_date' => $this->date($custom->shipment?->warehouse_date),
            'eta' => $this->date($custom->shipment?->eta),
            'doc_submission_date' => $this->date($custom->doc_submission_date),
            'days_in_customs' => $custom->days_in_customs,
            'clearance_date' => $this->date($custom->clearance_date),
            'customs_quantity' => $custom->shipment?->customs_quantity !== null ? delimiter($custom->shipment?->customs_quantity) : null,
            'ten_percent_exit_date' => $this->date($custom->ten_percent_exit_date),
            'bank_guarantee_status' => $custom->bankGuaranteeStatus?->getLocalizedNameAttribute(),
            'commitment_status' => $custom->commitmentStatus?->getLocalizedNameAttribute(),
            'doc_status' => $custom->shipment?->docStatus?->getLocalizedNameAttribute(),
        ];
    }

    private function applyQuick(Builder $query, ?string $quick, ReportFilters $filters): Builder
    {
        $predicate = $this->quickPredicate($quick, $filters);

        if ($predicate === null) {
            return $filters->showClosed ? $query : CustomChecks::open($query, $filters);
        }

        $scope = in_array($quick, self::GUARANTEE_QUICK, true) ? $query : CustomChecks::open($query, $filters);

        return $predicate($scope);
    }

    private function quickPredicate(?string $quick, ReportFilters $filters): ?Closure
    {
        if ($quick !== null && preg_match('/^stage_(\d+)$/', $quick, $match)) {
            return fn (Builder $query): Builder => $this->stagePredicate($query, (int) $match[1]);
        }

        return match ($quick) {
            'open_files' => fn (Builder $query): Builder => $query,
            'no_status' => fn (Builder $query): Builder => $query->whereRaw($this->noStatusSql()),
            'over_threshold' => fn (Builder $query): Builder => $query->whereRaw($this->overSql($filters)),
            'no_basis' => fn (Builder $query): Builder => $query->whereRaw($this->noBasisSql($filters)),
            'ten_percent' => fn (Builder $query): Builder => $query->whereRaw($this->tenPercentSql()),
            'guarantees_not_returned' => fn (Builder $query): Builder => $this->notReturnedPredicate($query),
            'guarantees_cleared' => fn (Builder $query): Builder => $this->completedPredicate($this->notReturnedPredicate($query), $filters),
            default => null,
        };
    }

    private function noStatusSql(): string
    {
        $valid = CustomChecks::validIdList();

        return $valid === [] ? '(1 = 1)' : '(customs.clearance_status_id is null or customs.clearance_status_id not in ('.implode(',', $valid).'))';
    }

    private function noBasisSql(ReportFilters $filters): string
    {
        return '(('.CustomChecks::basisExpression($filters).') is null)';
    }

    private function overSql(ReportFilters $filters): string
    {
        $basis = CustomChecks::basisExpression($filters);
        $threshold = $filters->threshold('stuck');

        return "(({$basis}) is not null and DATEDIFF(COALESCE(customs.clearance_date, ".SqlToday::literal()."), {$basis}) > {$threshold})";
    }

    private function tenPercentSql(): string
    {
        $tenPercent = CustomChecks::statusId(Custom::TYPE_CLEARANCE_STATUS, ClearanceStatus::TenPercentRemaining->value);

        return $tenPercent ? '(customs.clearance_status_id = '.(int) $tenPercent.')' : '(0 = 1)';
    }

    private function stagePredicate(Builder $query, int $id): Builder
    {
        return in_array($id, CustomChecks::validIdList(), true)
            ? $query->where('customs.clearance_status_id', $id)
            : $query->whereRaw('0 = 1');
    }

    private function notReturnedPredicate(Builder $query): Builder
    {
        $notReturned = CustomChecks::statusId(Custom::TYPE_BANK_GUARANTEE_STATUS, GuaranteeStatus::NotReturned->value) ?? 0;

        return $query->where('customs.bank_guarantee_status_id', $notReturned);
    }

    private function completedPredicate(Builder $query, ReportFilters $filters): Builder
    {
        return $query->whereRaw($this->completedSql($filters));
    }

    private function openStats(ReportFilters $filters): object
    {
        return $this->joinShipment($this->openQuery(Custom::query(), $filters))
            ->selectRaw('count(*) as total')
            ->selectRaw("coalesce(sum(case when {$this->noStatusSql()} then 1 else 0 end), 0) as no_status")
            ->selectRaw("coalesce(sum(case when {$this->noBasisSql($filters)} then 1 else 0 end), 0) as no_basis")
            ->selectRaw("coalesce(sum(case when {$this->overSql($filters)} then 1 else 0 end), 0) as over_threshold")
            ->selectRaw("coalesce(sum(case when {$this->tenPercentSql()} then 1 else 0 end), 0) as ten_percent")
            ->first();
    }

    private function openCard(object $stats): array
    {
        return $this->card('open', [
            $this->line(__(self::LANG.'lines.count'), (int) $stats->total, 'open_files'),
            $this->line(__(self::LANG.'lines.no_status'), (int) $stats->no_status, 'no_status'),
        ]);
    }

    private function stageCard(ReportFilters $filters): array
    {
        $counts = $this->stageCounts($this->openQuery(Custom::query(), $filters));
        $lines = $this->orderedStages()
            ->map(fn (Status $status): array => $this->line((string) $status->getLocalizedNameAttribute(), (int) ($counts[$status->id] ?? 0), 'stage_'.$status->id))
            ->all();

        return $this->card('per_stage', $lines);
    }

    private function delaysCard(object $stats): array
    {
        return $this->card('over_threshold', [
            $this->line(__(self::LANG.'lines.over'), (int) $stats->over_threshold, 'over_threshold'),
            $this->line(__(self::LANG.'lines.no_basis'), (int) $stats->no_basis, 'no_basis'),
            $this->line(__(self::LANG.'lines.ten_percent'), (int) $stats->ten_percent, 'ten_percent'),
        ]);
    }

    private function guaranteesCard(ReportFilters $filters): array
    {
        $row = $this->notReturnedPredicate(CustomChecks::scoped(Custom::query(), $filters))
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(case when '.$this->completedSql($filters).' then 1 else 0 end), 0) as completed')
            ->first();

        return $this->card('guarantees', [
            $this->line(__(self::LANG.'lines.count'), (int) $row->total, 'guarantees_not_returned'),
            $this->line(__(self::LANG.'lines.of_completed'), (int) $row->completed, 'guarantees_cleared'),
            ['label' => __(self::LANG.'lines.guarantee_note'), 'value' => '-'],
        ]);
    }

    private function completedSql(ReportFilters $filters): string
    {
        $ids = array_map('intval', CustomChecks::clearedIds($filters));

        return $ids === [] ? '(0 = 1)' : '(customs.clearance_status_id in ('.implode(',', $ids).'))';
    }

    public static function agingColor(?int $days, int $threshold): string
    {
        return match (true) {
            $days === null => 'gray',
            $days > $threshold => 'danger',
            $days * 2 >= $threshold => 'warning',
            default => 'success',
        };
    }

    public static function sellerNameSql(): string
    {
        $column = app()->getLocale() === 'fa' ? 'name' : 'english_name';

        return "(select companies.{$column} from registered_orders join companies on companies.id = registered_orders.seller_id where registered_orders.id = customs.registered_order_id)";
    }

    public function stageOrderSql(): string
    {
        $ids = $this->orderedStages()->map(fn (Status $status): int => (int) $status->id)->all();

        return $ids === [] ? 'customs.clearance_status_id' : 'FIELD(customs.clearance_status_id, '.implode(',', $ids).')';
    }

    private function stageCounts(Builder $query): Collection
    {
        $valid = CustomChecks::validIdList();

        return $query
            ->whereNotNull('customs.clearance_status_id')
            ->when($valid !== [], fn (Builder $customs): Builder => $customs->whereIn('customs.clearance_status_id', $valid))
            ->selectRaw('clearance_status_id, count(*) as total')
            ->groupBy('clearance_status_id')
            ->pluck('total', 'clearance_status_id');
    }

    private function orderedStages(): Collection
    {
        $valid = CustomChecks::validIdList();

        $stages = app(ReportStages::class);
        $sequence = $stages->ordered(Custom::TYPE_CLEARANCE_STATUS);
        $apart = Status::query()->where('english_type', Custom::TYPE_CLEARANCE_STATUS)->whereIn('id', $valid)->whereNotIn('id', $sequence->pluck('id'))->orderBy('id')->get();

        return $sequence->concat($apart)->filter(fn (Status $status): bool => in_array($status->id, $valid))->values();
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

    private function monthKey(string $date): string
    {
        if (isJalaliCalendar()) {
            return jdate($date)->format('Y/m');
        }

        return substr($date, 0, 7);
    }

    private function visible(Builder $query, ReportFilters $filters): Builder
    {
        return $filters->showClosed
            ? CustomChecks::scoped($query, $filters)
            : $this->openQuery($query, $filters);
    }

    private function openQuery(Builder $query, ReportFilters $filters): Builder
    {
        return CustomChecks::open(CustomChecks::scoped($query, $filters), $filters);
    }

    private function joinShipment(Builder $query): Builder
    {
        return $query->leftJoinSub(
            DB::table('shipments')->select('id', 'warehouse_date', 'eta')->whereNull('deleted_at'),
            's',
            fn (JoinClause $join) => $join->on('s.id', '=', 'customs.shipment_id'),
        );
    }

    private function card(string $key, array $lines): array
    {
        return ['key' => $key, 'label' => __(self::LANG."cards.{$key}"), 'lines' => $lines, 'tone' => null];
    }

    private function line(string $label, mixed $value, string $quick): array
    {
        return ['label' => $label, 'value' => (string) $value, 'quick' => $quick];
    }

    private function date(mixed $date): ?string
    {
        return $date ? adaptiveDate($date) : null;
    }
}
