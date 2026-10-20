<?php

namespace App\Services\Reports\Support;

use App\Filament\Resources\Operational\BankProfileResource\Enums\Status as BankProfileStatus;
use App\Models\BankProfile;
use App\Models\Shipment;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Checks\InTransitChecks;
use App\Services\Reports\Rules\FxRateService;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Rules\ShipmentCurrencyResolver;
use App\Services\SmartCacheManager;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class TransitLedger
{
    public const FLAG_NO_VALUE = 1;

    public const FLAG_NO_CURRENCY = 2;

    public const FLAG_MISMATCH = 4;

    public const FLAG_OUTLIER = 8;

    public const FLAG_NO_RATE = 16;

    public const FLAG_CURRENCY_INFERRED = 32;

    public const FLAGS = [
        'no_value' => self::FLAG_NO_VALUE,
        'no_currency' => self::FLAG_NO_CURRENCY,
        'mismatch' => self::FLAG_MISMATCH,
        'outlier' => self::FLAG_OUTLIER,
        'no_rate' => self::FLAG_NO_RATE,
        'currency_inferred' => self::FLAG_CURRENCY_INFERRED,
    ];

    public const SUPPLY = ['full', 'partial', 'none', 'allocated', 'unknown'];

    public const SUPPLY_UNKNOWN = 4;

    private const MEMO_SECONDS = 30;

    private const DERIVED_COLUMNS = ['id', 'value', 'currency_id', 'eur', 'reason', 'flags', 'supply', 'ratio', 'supplied', 'order_value', 'order_currency_id', 'weight', 'estimated', 'exposure'];

    public static int $cap = 2000;

    private static array $memo = [];

    private static array $last = [];

    public static function flush(): void
    {
        self::$memo = [];
        self::$last = [];
        CheckRegistry::invalidate();
    }

    /**
     * @return array{over: bool, rows: array<int, array<string, mixed>>}
     */
    public static function rows(ReportFilters $filters): array
    {
        $key = $filters->cacheKey();
        $memo = self::$memo[$key] ?? null;

        if ($memo === null || $memo['at'] < now()->getTimestamp() - self::MEMO_SECONDS) {
            $memo = self::$memo[$key] = [
                'at' => now()->getTimestamp(),
                'ledger' => SmartCacheManager::remember('ReportCheck', ['ledger' => 'executive', 'filters' => $key], 5, fn (): array => self::build($filters)),
            ];
        }

        self::$last = $memo['ledger']['rows'];

        return $memo['ledger'];
    }

    public static function row(int $id): ?array
    {
        return self::$last[$id] ?? null;
    }

    /**
     * @return array<int, int>
     */
    public static function idsWhere(ReportFilters $filters, Closure $predicate, string $field = 'id'): array
    {
        return collect(self::rows($filters)['rows'])
            ->filter($predicate)
            ->pluck($field)
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function population(ReportFilters $filters): Builder
    {
        return InTransitChecks::scoped(Shipment::query(), $filters)->whereRaw(InTransitChecks::inTransitSql($filters));
    }

    public static function derivedSql(array $rows): string
    {
        $aliases = array_map(fn (string $column): string => 'led_'.($column === 'id' ? 'shipment_id' : $column), self::DERIVED_COLUMNS);

        if ($rows === []) {
            return 'select '.implode(', ', array_map(fn (string $alias): string => "0 as {$alias}", $aliases)).' from dual where 1 = 0';
        }

        $selects = [];

        foreach (array_values($rows) as $index => $row) {
            $values = array_map(fn (string $column, string $alias): string => self::literal($row[$column] ?? null).($index === 0 ? " as {$alias}" : ''), self::DERIVED_COLUMNS, $aliases);
            $selects[] = 'select '.implode(', ', $values);
        }

        return implode(' union all ', $selects);
    }

    private static function literal(mixed $value): string
    {
        if ($value === null || (is_float($value) && ! is_finite($value))) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_int($value) ? (string) $value : number_format((float) $value, 5, '.', '');
    }

    private static function build(ReportFilters $filters): array
    {
        $shipments = self::population($filters)
            ->leftJoin('registered_orders as ro', 'ro.id', '=', 'shipments.registered_order_id')
            ->leftJoinSub(self::itemsAggregate(), 'si', fn (JoinClause $join) => $join->on('si.shipment_id', '=', 'shipments.id'))
            ->leftJoinSub(self::orderAggregate(), 'oi', fn (JoinClause $join) => $join->on('oi.registered_order_id', '=', 'shipments.registered_order_id'))
            ->select('shipments.id', 'shipments.registered_order_id', 'shipments.shipped_quantity', 'shipments.grand_total', 'shipments.eta')
            ->selectRaw('ro.currency_id as order_currency_id, ro.seller_id as seller_id')
            ->selectRaw('si.items_total, si.gross, si.net, si.item_count')
            ->selectRaw('oi.order_total as order_value, oi.qty as order_qty, oi.units as order_units, oi.gross as order_gross, oi.net as order_net')
            ->selectRaw('(select count(*) from shipments as sibling where sibling.registered_order_id = shipments.registered_order_id and sibling.deleted_at is null and '.str_replace('shipments.', 'sibling.', InTransitChecks::voidSql()).') as live_count')
            ->orderBy('shipments.id')
            ->limit(self::$cap + 1)
            ->get();

        if ($shipments->count() > self::$cap) {
            return ['over' => true, 'rows' => []];
        }

        $resolved = app(ShipmentCurrencyResolver::class)->resolveMany($shipments);
        $fx = app(FxRateService::class);
        $rows = [];
        $shipped = $shipments->whereNotNull('registered_order_id')->groupBy('registered_order_id')
            ->map(fn ($group): float => (float) $group->sum(fn (Shipment $shipment): float => is_numeric($shipment->shipped_quantity) ? (float) $shipment->shipped_quantity : 0.0));

        foreach ($shipments as $shipment) {
            $rows[$shipment->id] = [
                ...self::shipmentRow($shipment, $resolved[$shipment->id], $fx, $filters),
                'over_shipped' => $shipment->registered_order_id !== null && (float) ($shipment->order_qty ?? 0) > 0 && (int) $shipment->order_units === 1 && round($shipped[$shipment->registered_order_id] ?? 0.0, 5) > round((float) $shipment->order_qty, 5),
            ];
        }

        $supply = self::supplyByOrder($shipments, $fx, $filters);

        foreach ($rows as $id => $row) {
            $order = $supply[$row['order_id']] ?? ['supply' => self::SUPPLY_UNKNOWN, 'ratio' => null, 'supplied' => null];
            $rows[$id] = [...$row, ...$order, 'exposure' => $row['reason'] === 0 && $order['ratio'] !== null ? $row['eur'] * (1 - min($order['ratio'], 1.0)) : null];
        }

        return ['over' => false, 'rows' => $rows];
    }

    private static function shipmentRow(Shipment $shipment, array $resolved, FxRateService $fx, ReportFilters $filters): array
    {
        $grand = (float) ($shipment->grand_total ?? 0);
        $itemsTotal = (float) ($shipment->items_total ?? 0);
        $share = self::share($shipment);
        $flags = 0;
        $value = null;
        $currency = null;
        $estimated = false;

        if ($grand > 0) {
            $value = $grand;
            $currency = $resolved['currency_id'];
            $flags |= $currency === null ? self::FLAG_NO_CURRENCY : 0;
            $flags |= $currency !== null && $resolved['source'] !== 'invoice' ? self::FLAG_CURRENCY_INFERRED : 0;
            $flags |= $resolved['mismatch'] ? self::FLAG_MISMATCH : 0;
            $flags |= $itemsTotal > 0 && $grand > $filters->threshold('outlier_factor') * $itemsTotal ? self::FLAG_OUTLIER : 0;
        } elseif ((float) ($shipment->order_value ?? 0) > 0 && $share !== null) {
            $value = (float) $shipment->order_value * $share;
            $currency = $shipment->order_currency_id !== null ? (int) $shipment->order_currency_id : null;
            $estimated = true;
            $flags |= $currency === null ? self::FLAG_NO_CURRENCY : 0;
        } else {
            $flags |= self::FLAG_NO_VALUE;
        }

        $eur = $value !== null && $currency !== null ? $fx->toEur($value, $currency) : null;
        $flags |= $value !== null && $currency !== null && $eur === null ? self::FLAG_NO_RATE : 0;
        $reason = self::reason($flags, $filters);
        $counted = $reason === 0;

        return [
            'id' => (int) $shipment->id,
            'order_id' => $shipment->registered_order_id !== null ? (int) $shipment->registered_order_id : null,
            'seller_id' => $shipment->seller_id !== null ? (int) $shipment->seller_id : null,
            'eta' => $shipment->eta ? Carbon::parse($shipment->eta)->toDateString() : null,
            'value' => $value,
            'currency_id' => $currency,
            'invoice_currency_id' => $resolved['source'] === 'invoice' ? $resolved['currency_id'] : null,
            'order_currency_id' => $shipment->order_currency_id !== null ? (int) $shipment->order_currency_id : null,
            'order_value' => $shipment->order_value !== null ? (float) $shipment->order_value : null,
            'eur' => $counted ? $eur : null,
            'rate' => $counted && $value > 0 ? $eur / $value : null,
            'reason' => $reason,
            'flags' => $flags,
            'weight' => self::weight($shipment, $share, $filters),
            'estimated' => $estimated,
            'charges' => $grand > 0 ? $grand - $itemsTotal : 0.0,
            'items_total' => $itemsTotal,
            'share' => $share,
        ];
    }

    private static function reason(int $flags, ReportFilters $filters): int
    {
        return match (true) {
            (bool) ($flags & self::FLAG_NO_VALUE) => 1,
            (bool) ($flags & self::FLAG_NO_CURRENCY) => 2,
            ($flags & self::FLAG_MISMATCH) && ! $filters->includeFlaggedInvoices => 3,
            ($flags & self::FLAG_OUTLIER) && ! $filters->includeOutliers => 4,
            (bool) ($flags & self::FLAG_NO_RATE) => 5,
            default => 0,
        };
    }

    private static function share(Shipment $shipment): ?float
    {
        $quantity = (float) ($shipment->order_qty ?? 0);
        $shipped = $shipment->shipped_quantity;

        if ($quantity > 0 && (int) $shipment->order_units === 1 && is_numeric($shipped) && (float) $shipped > 0) {
            return min(1.0, (float) $shipped / $quantity);
        }

        return (int) $shipment->live_count === 1 && $shipment->order_value !== null ? 1.0 : null;
    }

    private static function weight(Shipment $shipment, ?float $share, ReportFilters $filters): ?float
    {
        $gross = $filters->weightBasis === 'gross';
        $items = $gross ? $shipment->gross : $shipment->net;

        if ((int) $shipment->item_count > 0 && $items !== null) {
            return (float) $items;
        }

        $order = $gross ? $shipment->order_gross : $shipment->order_net;

        return $order !== null && $share !== null ? (float) $order * $share : null;
    }

    private static function supplyByOrder(iterable $shipments, FxRateService $fx, ReportFilters $filters): array
    {
        $orders = [];

        foreach ($shipments as $shipment) {
            if ($shipment->registered_order_id !== null) {
                $orders[(int) $shipment->registered_order_id] = [
                    'value' => (float) ($shipment->order_value ?? 0),
                    'currency' => $shipment->order_currency_id !== null ? (int) $shipment->order_currency_id : null,
                ];
            }
        }

        $stages = app(ReportStages::class);
        $suppliedIds = collect([BankProfileStatus::Purchased, BankProfileStatus::Received])->map(fn (BankProfileStatus $status): ?int => $stages->statusByName(BankProfile::TYPE_BANK_PROFILE, $status->value)?->id)->filter()->map(fn ($id): int => (int) $id)->all();
        $allocatedId = $stages->statusByName(BankProfile::TYPE_BANK_PROFILE, BankProfileStatus::Allocated->value)?->id;
        $profiles = BankProfile::query()
            ->whereIn('registered_order_id', array_keys($orders))
            ->whereIn('status_id', array_filter([...$suppliedIds, $allocatedId]))
            ->toBase()
            ->selectRaw('registered_order_id, status_id, purchased_currency_id, SUM(COALESCE(purchased_equivalent, 0)) as amount')
            ->groupBy('registered_order_id', 'status_id', 'purchased_currency_id')
            ->get()
            ->groupBy('registered_order_id');
        $result = [];

        foreach ($orders as $orderId => $order) {
            $result[$orderId] = self::orderSupply($order, $profiles->get($orderId, collect()), $suppliedIds, $allocatedId, $fx, $filters);
        }

        return $result;
    }

    private static function orderSupply(array $order, iterable $profiles, array $suppliedIds, ?int $allocatedId, FxRateService $fx, ReportFilters $filters): array
    {
        $supplied = 0.0;
        $unknown = $order['currency'] === null || $order['value'] <= 0;
        $allocated = false;

        foreach ($profiles as $profile) {
            if ($allocatedId !== null && (int) $profile->status_id === $allocatedId) {
                $allocated = true;

                continue;
            }

            $amount = (float) $profile->amount;

            if ($amount <= 0 || $unknown) {
                continue;
            }

            $rate = $profile->purchased_currency_id === null ? null : $fx->rate((int) $profile->purchased_currency_id, $order['currency']);

            if ($rate === null) {
                $unknown = true;

                continue;
            }

            $supplied += $amount * $rate['rate'];
        }

        if ($unknown) {
            return ['supply' => self::SUPPLY_UNKNOWN, 'ratio' => null, 'supplied' => null];
        }

        $full = $filters->threshold('supply_full');
        $supply = match (true) {
            $supplied * 100 > $full * $order['value'] => 0,
            $supplied > 0 => 1,
            $allocated => 3,
            default => 2,
        };

        return ['supply' => $supply, 'ratio' => $supplied / $order['value'], 'supplied' => $supplied];
    }

    private static function itemsAggregate(): QueryBuilder
    {
        return DB::table('shipment_items')
            ->select('shipment_id')
            ->selectRaw('SUM(total_amount) as items_total, SUM(gross_weight) as gross, SUM(net_weight) as net, COUNT(*) as item_count')
            ->groupBy('shipment_id');
    }

    private static function orderAggregate(): QueryBuilder
    {
        return DB::table('registered_order_items')
            ->whereNull('deleted_at')
            ->select('registered_order_id')
            ->selectRaw('SUM(COALESCE(line_total, quantity * unit_price)) as order_total, SUM(quantity) as qty, COUNT(DISTINCT unit) as units, SUM(gross_weight) as gross, SUM(net_weight) as net')
            ->groupBy('registered_order_id');
    }
}
