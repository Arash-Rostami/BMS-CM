<?php

namespace App\Services\Reports\Support;

use App\Models\BankProfile;
use App\Models\Custom;
use App\Models\Shipment;
use App\Services\Reports\Checks\CustomChecks;
use App\Services\Reports\Checks\FxCommitmentChecks;
use App\Services\Reports\Checks\InTransitChecks;
use App\Services\Reports\Rules\FxRateService;
use App\Services\Reports\Rules\ShipmentCurrencyResolver;
use App\Services\SmartCacheManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FxCommitmentLedger
{
    public const MISMATCH = 1;

    public const OUTLIER = 2;

    public const VOID = 4;

    public const NO_INVOICE = 8;

    public const NO_RATE = 16;

    public const ESTIMATED = 32;

    public const NO_CURRENCY = 64;

    public const CURRENCY_INFERRED = 128;

    public const FLAG_KEYS = [
        self::MISMATCH => 'mismatch',
        self::OUTLIER => 'outlier',
        self::VOID => 'void',
        self::NO_INVOICE => 'no_invoice',
        self::NO_RATE => 'no_rate',
        self::ESTIMATED => 'estimated',
        self::NO_CURRENCY => 'no_currency',
        self::CURRENCY_INFERRED => 'currency_inferred',
    ];

    public static int $maxOrders = 2000;

    private const TTL_MINUTES = 5;

    private const DECIMALS = 5;

    private array $memo = [];

    public function __construct(private readonly FxRateService $fx, private readonly ShipmentCurrencyResolver $resolver) {}

    public function key(ReportFilters $filters): string
    {
        return md5(json_encode([
            CustomChecks::clearedIds($filters),
            $filters->clearedFromStage,
            $filters->includeVoid,
            $filters->includeFlaggedInvoices,
            $filters->includeOutliers,
            $filters->fallbackOrderAmount,
            $filters->valueBasis,
            $filters->threshold('outlier_factor'),
            $filters->registeredOrderId,
            $filters->rateBasis,
            self::$maxOrders,
            today()->toDateString(),
        ]));
    }

    /**
     * @return array{orders: array<int, array{resolved: float, cleared: int, used: int, flags: int}>, shipments: array<int, array{o: int, cond: int, flags: int}>, cleared: int, unusable: int}
     */
    public function forFilters(ReportFilters $filters): array
    {
        $key = $this->key($filters);

        return $this->memo[$key] ??= SmartCacheManager::remember('ReportCheck', ['ledger' => 'fx_commitments', 'key' => $key], self::TTL_MINUTES, fn (): array => $this->build($filters));
    }

    public function literalSql(ReportFilters $filters): string
    {
        $rows = collect($this->forFilters($filters)['orders'])
            ->map(fn (array $order, int|string $id): string => sprintf(
                'select %d as oid, %s as resolved, %d as cleared, %d as used, %d as flags',
                $id,
                number_format($order['resolved'], self::DECIMALS, '.', ''),
                $order['cleared'],
                $order['used'],
                $order['flags'],
            ))
            ->values();

        return $rows->isEmpty()
            ? 'select 0 as oid, 0 as resolved, 0 as cleared, 0 as used, 0 as flags from dual where 1 = 0'
            : $rows->implode(' union all ');
    }

    /**
     * @return array<int, int>
     */
    public function shipmentIdsWith(ReportFilters $filters, int $condition): array
    {
        return collect($this->forFilters($filters)['shipments'])
            ->filter(fn (array $shipment): bool => ($shipment['cond'] & $condition) !== 0)
            ->keys()
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function orderIdsWithFlag(ReportFilters $filters, int $flag): array
    {
        return collect($this->forFilters($filters)['orders'])
            ->filter(fn (array $order): bool => ($order['flags'] & $flag) !== 0)
            ->keys()
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function orderIds(ReportFilters $filters): array
    {
        return collect($this->forFilters($filters)['orders'])->keys()->map(fn (int|string $id): int => (int) $id)->values()->all();
    }

    /**
     * @return array<int, int>
     */
    public function overResolvedOrderIds(ReportFilters $filters): array
    {
        $orders = collect($this->forFilters($filters)['orders'])->filter(fn (array $order): bool => $order['resolved'] > 0);

        if ($orders->isEmpty()) {
            return [];
        }

        $tolerance = 1 + $filters->threshold('exceed_pct') / 100;
        $purchased = $this->purchasedTotals($orders->keys()->all());

        return $orders
            ->filter(fn (array $order, int|string $id): bool => isset($purchased[$id]) && $purchased[$id]->total > 0 && $order['resolved'] > (float) $purchased[$id]->total * $tolerance)
            ->keys()
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array{cleared: int, unusable: int, oversize: bool}
     */
    public function summary(ReportFilters $filters): array
    {
        $ledger = $this->forFilters($filters);

        return ['cleared' => $ledger['cleared'], 'unusable' => $ledger['unusable'], 'oversize' => $ledger['over']];
    }

    private function build(ReportFilters $filters): array
    {
        $cleared = CustomChecks::clearedIds($filters);
        $files = $cleared === [] ? collect() : $this->files($filters, $cleared);
        $orderIds = $files->pluck('oid')->unique()->values()->all();
        $currencies = $this->purchasedCurrencies($orderIds);
        $orders = $filters->fallbackOrderAmount ? $this->orderValues($orderIds) : [];
        $pairs = $filters->rateBasis === 'order' ? $this->orderPairs($orderIds) : [];
        $resolved = $this->resolver->resolveMany(Shipment::query()->whereIn('id', $files->pluck('sid')->all())->get(['id', 'registered_order_id']));

        $ledger = ['orders' => [], 'shipments' => [], 'cleared' => $files->count(), 'unusable' => 0, 'over' => false];

        foreach ($files as $file) {
            $row = $this->shipment($file, $resolved[(int) $file->sid], $orders[$file->oid] ?? null, $currencies[$file->oid] ?? null, $pairs[$file->oid] ?? [], $filters);
            $ledger['shipments'][(int) $file->sid] = ['o' => (int) $file->oid, 'cond' => $row['cond'], 'flags' => $row['flags']];
            $ledger['unusable'] += ($row['flags'] & self::NO_INVOICE) !== 0 ? 1 : 0;
            $ledger['orders'][(int) $file->oid] = $this->accumulate($ledger['orders'][(int) $file->oid] ?? ['resolved' => 0.0, 'cleared' => 0, 'used' => 0, 'flags' => 0], $row);
        }

        if (count($ledger['orders']) > self::$maxOrders) {
            return [...$ledger, 'orders' => [], 'over' => true];
        }

        return $ledger;
    }

    private function accumulate(array $order, array $row): array
    {
        return [
            'resolved' => round($order['resolved'] + ($row['value'] ?? 0), self::DECIMALS),
            'cleared' => $order['cleared'] + 1,
            'used' => $order['used'] + ($row['value'] !== null ? 1 : 0),
            'flags' => $order['flags'] | $row['flags'],
        ];
    }

    private function shipment(object $file, array $currency, ?array $order, ?int $purchased, array $pairs, ReportFilters $filters): array
    {
        $grand = (float) $file->grand;
        $items = (float) $file->items;
        $factor = $filters->threshold('outlier_factor');
        $itemsBasis = $filters->valueBasis === 'items_total';
        $value = ($itemsBasis ? $items : $grand) > 0 ? ($itemsBasis ? $items : $grand) : null;
        $currencyId = $currency['currency_id'];
        $cond = ($file->is_void ? self::VOID : 0)
            | ($currency['mismatch'] ? self::MISMATCH : 0)
            | ($grand > 0 && $items > 0 && $grand > $factor * $items ? self::OUTLIER : 0)
            | ($value === null ? self::NO_INVOICE : 0)
            | ($value !== null && $currencyId === null ? self::NO_CURRENCY : 0);
        $flags = 0;
        $estimated = false;

        if ($value === null && $filters->fallbackOrderAmount && $order !== null && $order['ro'] > 0 && $order['qty'] > 0 && (float) $file->shipped > 0) {
            $value = $order['ro'] * min(1, (float) $file->shipped / $order['qty']);
            $currencyId = $order['currency'];
            $estimated = true;
            $flags |= self::ESTIMATED;
            $cond &= ~self::NO_CURRENCY;
            $cond |= $currencyId === null ? self::NO_CURRENCY : 0;
        }

        $flags |= $this->exclusions($cond, $estimated, $filters, $itemsBasis);
        $converted = null;

        if (($flags & ~self::ESTIMATED) === 0 && $purchased !== null) {
            $converted = $this->convert($value, (int) $currencyId, $purchased, $pairs, $filters);

            if ($converted === null) {
                $cond |= self::NO_RATE;
                $flags |= self::NO_RATE;
            }
        }

        if ($value !== null && ! $estimated && $currency['source'] !== null && $currency['source'] !== 'invoice') {
            $cond |= self::CURRENCY_INFERRED;
            $flags |= self::CURRENCY_INFERRED;
        }

        return ['cond' => $cond, 'flags' => $flags, 'value' => $converted];
    }

    private function exclusions(int $cond, bool $estimated, ReportFilters $filters, bool $itemsBasis): int
    {
        return (($cond & self::VOID) && ! $filters->includeVoid ? self::VOID : 0)
            | (! $estimated && ($cond & self::MISMATCH) && ! $filters->includeFlaggedInvoices ? self::MISMATCH : 0)
            | (! $estimated && ! $itemsBasis && ($cond & self::OUTLIER) && ! $filters->includeOutliers ? self::OUTLIER : 0)
            | (! $estimated && ($cond & self::NO_INVOICE) ? self::NO_INVOICE : 0)
            | ($cond & self::NO_CURRENCY);
    }

    private function convert(?float $value, int $from, int $to, array $pairs, ReportFilters $filters): ?float
    {
        $rate = match (true) {
            $from === $to => 1.0,
            $filters->rateBasis === 'order' => $pairs["{$from}:{$to}"] ?? null,
            default => $this->fx->rate($from, $to)['rate'] ?? null,
        };

        return $value === null || $rate === null ? null : round($value * $rate, self::DECIMALS);
    }

    private function orderPairs(array $orderIds): array
    {
        $pairs = [];

        $rows = BankProfile::query()
            ->whereIn('bank_profiles.status_id', FxCommitmentChecks::countedIds())
            ->whereIn('bank_profiles.registered_order_id', $orderIds)
            ->where('bank_profiles.requested_amount', '>', 0)
            ->where('bank_profiles.purchased_equivalent', '>', 0)
            ->whereNotNull('bank_profiles.requested_currency_id')
            ->whereNotNull('bank_profiles.purchased_currency_id')
            ->groupBy('bank_profiles.registered_order_id', 'bank_profiles.requested_currency_id', 'bank_profiles.purchased_currency_id')
            ->selectRaw('bank_profiles.registered_order_id as oid, bank_profiles.requested_currency_id as from_id, bank_profiles.purchased_currency_id as to_id, sum(bank_profiles.purchased_equivalent) as equivalent, sum(bank_profiles.requested_amount) as requested')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $pairs[(int) $row->oid]["{$row->from_id}:{$row->to_id}"] = (float) $row->equivalent / (float) $row->requested;
        }

        return $pairs;
    }

    private function files(ReportFilters $filters, array $cleared): Collection
    {
        return Custom::query()
            ->whereIn('customs.clearance_status_id', $cleared)
            ->when($filters->registeredOrderId, fn ($customs, int $id) => $customs->where('customs.registered_order_id', $id))
            ->join('shipments', fn ($join) => $join->on('shipments.id', '=', 'customs.shipment_id')->whereNull('shipments.deleted_at'))
            ->selectRaw('shipments.id as sid, coalesce(customs.registered_order_id, shipments.registered_order_id) as oid, shipments.grand_total as grand, shipments.shipped_quantity as shipped')
            ->selectRaw('(select sum(shipment_items.total_amount) from shipment_items where shipment_items.shipment_id = shipments.id) as items')
            ->selectRaw('(not '.InTransitChecks::voidSql().') as is_void')
            ->orderBy('customs.id')
            ->toBase()
            ->get()
            ->unique('sid')
            ->values();
    }

    private function purchasedCurrencies(array $orderIds): array
    {
        return BankProfile::query()
            ->whereIn('bank_profiles.status_id', FxCommitmentChecks::countedIds())
            ->whereIn('bank_profiles.registered_order_id', $orderIds)
            ->groupBy('bank_profiles.registered_order_id')
            ->havingRaw('count(distinct bank_profiles.purchased_currency_id) = 1 and sum(case when bank_profiles.purchased_currency_id is null then 1 else 0 end) = 0')
            ->selectRaw('bank_profiles.registered_order_id as oid, min(bank_profiles.purchased_currency_id) as pcur')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->oid => (int) $row->pcur])
            ->all();
    }

    private function purchasedTotals(array $orderIds): Collection
    {
        return BankProfile::query()
            ->whereIn('bank_profiles.status_id', FxCommitmentChecks::countedIds())
            ->whereIn('bank_profiles.registered_order_id', $orderIds)
            ->groupBy('bank_profiles.registered_order_id')
            ->havingRaw('count(distinct bank_profiles.purchased_currency_id) = 1 and sum(case when bank_profiles.purchased_currency_id is null then 1 else 0 end) = 0')
            ->selectRaw('bank_profiles.registered_order_id as oid, sum(bank_profiles.purchased_equivalent) as total')
            ->toBase()
            ->get()
            ->keyBy('oid');
    }

    private function orderValues(array $orderIds): array
    {
        $currencies = DB::table('registered_orders')->whereIn('id', $orderIds)->pluck('currency_id', 'id');

        return DB::table('registered_order_items')
            ->whereNull('deleted_at')
            ->whereIn('registered_order_id', $orderIds)
            ->groupBy('registered_order_id')
            ->selectRaw('registered_order_id as oid, sum(coalesce(line_total, quantity * unit_price)) as ro, sum(quantity) as qty')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->oid => ['ro' => (float) $row->ro, 'qty' => (float) $row->qty, 'currency' => isset($currencies[$row->oid]) ? (int) $currencies[$row->oid] : null]])
            ->all();
    }
}
