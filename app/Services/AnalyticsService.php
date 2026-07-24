<?php

namespace App\Services;

use App\Models\RegisteredOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    protected const TTL = 300;

    public static function concentrationRisk(): array
    {
        return Cache::remember('analytics:concentration', static::TTL, function () {
            if (static::supportsCte()) {
                $result = DB::selectOne('
                    with supplier_spend as (
                        select sum(total_amount) as spend
                        from payments
                        where payee_id is not null and deleted_at is null
                        group by payee_id
                    ),
                    currency_spend as (
                        select sum(total_amount) as spend
                        from payments
                        where currency_id is not null and deleted_at is null
                        group by currency_id
                    )
                    select
                        coalesce((select (sum(spend * spend) / nullif(power(sum(spend), 2), 0)) * 10000 from supplier_spend), 0) as supplier_hhi,
                        coalesce((select (sum(spend * spend) / nullif(power(sum(spend), 2), 0)) * 10000 from currency_spend), 0) as currency_hhi
                ');
            } else {
                $result = DB::selectOne('
                    select
                        coalesce((
                            select (sum(spend * spend) / nullif(power(sum(spend), 2), 0)) * 10000
                            from (
                                select sum(total_amount) as spend
                                from payments
                                where payee_id is not null and deleted_at is null
                                group by payee_id
                            ) supplier_spend
                        ), 0) as supplier_hhi,
                        coalesce((
                            select (sum(spend * spend) / nullif(power(sum(spend), 2), 0)) * 10000
                            from (
                                select sum(total_amount) as spend
                                from payments
                                where currency_id is not null and deleted_at is null
                                group by currency_id
                            ) currency_spend
                        ), 0) as currency_hhi
                ');
            }

            return [
                'supplier_hhi' => (float)($result->supplier_hhi ?? 0),
                'currency_hhi' => (float)($result->currency_hhi ?? 0),
            ];
        });
    }

    public static function cycleTimeByStage(): array
    {
        return Cache::remember('analytics:cycle_time', static::TTL, function () {
            $targetableType = RegisteredOrder::class;

            if (static::supportsCte()) {
                $rows = DB::select("
                    with durations as (
                        select 'request_to_order' as stage, datediff(ro.order_date, pr.approval_date) as duration
                        from purchase_requests pr
                        join registered_order_purchase_request pivot on pivot.purchase_request_id = pr.id
                        join registered_orders ro on ro.id = pivot.registered_order_id
                        where pr.deleted_at is null and ro.deleted_at is null
                          and pr.approval_date is not null and ro.order_date is not null
                          and ro.order_date >= pr.approval_date

                        union all

                        select 'order_to_payment', datediff(p.payment_date, ro.order_date)
                        from registered_orders ro
                        join payments p on p.targetable_id = ro.id and p.targetable_type = ?
                        where ro.deleted_at is null and p.deleted_at is null
                          and ro.order_date is not null and p.payment_date is not null
                          and p.payment_date >= ro.order_date

                        union all

                        select 'payment_to_shipment', datediff(s.eta, pay.first_payment_date)
                        from shipments s
                        join (
                            select targetable_id, min(payment_date) as first_payment_date
                            from payments
                            where targetable_type = ? and deleted_at is null and payment_date is not null
                            group by targetable_id
                        ) pay on pay.targetable_id = s.registered_order_id
                        where s.deleted_at is null and s.eta is not null
                          and s.eta >= pay.first_payment_date

                        union all

                        select 'shipment_to_clearance', datediff(c.clearance_date, s.exit_date)
                        from customs c
                        join shipments s on s.id = c.shipment_id
                        where c.deleted_at is null and s.deleted_at is null
                          and s.exit_date is not null and c.clearance_date is not null
                          and c.clearance_date >= s.exit_date
                    ),
                    ranked as (
                        select stage, duration,
                            row_number() over (partition by stage order by duration) as rn,
                            count(*) over (partition by stage) as cnt
                        from durations
                    )
                    select stage,
                        max(cnt) as count,
                        max(case when rn = floor((cnt - 1) * 0.5) + 1 then duration end) as p50,
                        max(case when rn = floor((cnt - 1) * 0.9) + 1 then duration end) as p90
                    from ranked
                    group by stage
                ", [$targetableType, $targetableType]);

                $byStage = [];
                foreach ($rows as $row) {
                    $byStage[$row->stage] = $row;
                }

                $stages = ['request_to_order', 'order_to_payment', 'payment_to_shipment', 'shipment_to_clearance'];
                $result = [];

                foreach ($stages as $stage) {
                    if (isset($byStage[$stage])) {
                        $result[$stage] = [
                            'count' => (int)$byStage[$stage]->count,
                            'p50' => $byStage[$stage]->p50 !== null ? (int)$byStage[$stage]->p50 : null,
                            'p90' => $byStage[$stage]->p90 !== null ? (int)$byStage[$stage]->p90 : null,
                        ];
                    } else {
                        $result[$stage] = ['count' => 0, 'p50' => null, 'p90' => null];
                    }
                }

                return $result;
            }

            $rows = DB::select("
                select 'request_to_order' as stage, datediff(ro.order_date, pr.approval_date) as duration
                from purchase_requests pr
                join registered_order_purchase_request pivot on pivot.purchase_request_id = pr.id
                join registered_orders ro on ro.id = pivot.registered_order_id
                where pr.deleted_at is null and ro.deleted_at is null
                  and pr.approval_date is not null and ro.order_date is not null
                  and ro.order_date >= pr.approval_date

                union all

                select 'order_to_payment' as stage, datediff(p.payment_date, ro.order_date) as duration
                from registered_orders ro
                join payments p on p.targetable_id = ro.id and p.targetable_type = ?
                where ro.deleted_at is null and p.deleted_at is null
                  and ro.order_date is not null and p.payment_date is not null
                  and p.payment_date >= ro.order_date

                union all

                select 'payment_to_shipment' as stage, datediff(s.eta, pay.first_payment_date) as duration
                from shipments s
                join (
                    select targetable_id, min(payment_date) as first_payment_date
                    from payments
                    where targetable_type = ? and deleted_at is null and payment_date is not null
                    group by targetable_id
                ) pay on pay.targetable_id = s.registered_order_id
                where s.deleted_at is null and s.eta is not null
                  and s.eta >= pay.first_payment_date

                union all

                select 'shipment_to_clearance' as stage, datediff(c.clearance_date, s.exit_date) as duration
                from customs c
                join shipments s on s.id = c.shipment_id
                where c.deleted_at is null and s.deleted_at is null
                  and s.exit_date is not null and c.clearance_date is not null
                  and c.clearance_date >= s.exit_date
            ", [$targetableType, $targetableType]);

            $stages = [
                'request_to_order' => [],
                'order_to_payment' => [],
                'payment_to_shipment' => [],
                'shipment_to_clearance' => [],
            ];

            foreach ($rows as $row) {
                $stages[$row->stage][] = (int)$row->duration;
            }

            foreach ($stages as &$durations) {
                sort($durations, SORT_NUMERIC);
            }
            unset($durations);

            return array_map(fn(array $durations) => [
                'count' => count($durations),
                'p50' => static::percentile($durations, 0.5),
                'p90' => static::percentile($durations, 0.9),
            ], $stages);
        });
    }

    public static function exposureAging(): array
    {
        return Cache::remember('analytics:exposure_aging', static::TTL, fn() => DB::table(function ($query) {
            $query->from('payments')
                ->select('currency_id')
                ->selectRaw('sum(case when payment_deadline >= curdate() - interval 30 day then payable_amount else 0 end) as bucket_0_30')
                ->selectRaw('sum(case when payment_deadline < curdate() - interval 30 day and payment_deadline >= curdate() - interval 60 day then payable_amount else 0 end) as bucket_31_60')
                ->selectRaw('sum(case when payment_deadline < curdate() - interval 60 day and payment_deadline >= curdate() - interval 90 day then payable_amount else 0 end) as bucket_61_90')
                ->selectRaw('sum(case when payment_deadline < curdate() - interval 90 day then payable_amount else 0 end) as bucket_90_plus')
                ->whereNull('deleted_at')
                ->whereNull('payment_date')
                ->whereNotNull('payment_deadline')
                ->where('payment_deadline', '<', DB::raw('curdate()'))
                ->groupBy('currency_id');
        }, 'p')
            ->join('currencies as c', fn($j) => $j->on('c.id', '=', 'p.currency_id')->whereNull('c.deleted_at'))
            ->select('c.id', 'c.name', 'c.english_name', 'p.bucket_0_30', 'p.bucket_31_60', 'p.bucket_61_90', 'p.bucket_90_plus')
            ->get()
            ->toArray());
    }

    public static function openCurrencyExposure(): array
    {
        return Cache::remember('analytics:open_exposure', static::TTL, fn() => DB::table(function ($query) {
            $items = DB::table('registered_order_items')
                ->select('registered_order_id', DB::raw('sum(line_total) as total'))
                ->whereNull('deleted_at')
                ->groupBy('registered_order_id');

            $payments = DB::table('payments')
                ->select('targetable_id', DB::raw('sum(total_amount) as total'))
                ->where('targetable_type', RegisteredOrder::class)
                ->whereNull('deleted_at')
                ->groupBy('targetable_id');

            $query->from('registered_orders as ro')
                ->leftJoinSub($items, 'items', 'items.registered_order_id', '=', 'ro.id')
                ->leftJoinSub($payments, 'paid', 'paid.targetable_id', '=', 'ro.id')
                ->whereNull('ro.deleted_at')
                ->groupBy('ro.currency_id')
                ->select('ro.currency_id')
                ->selectRaw('sum(greatest(coalesce(items.total, 0) - coalesce(paid.total, 0), 0)) as open_exposure')
                ->havingRaw('open_exposure > 0');
        }, 'e')
            ->join('currencies as c', fn($j) => $j->on('c.id', '=', 'e.currency_id')->whereNull('c.deleted_at'))
            ->select('c.id', 'c.name', 'c.english_name', 'e.open_exposure')
            ->get()
            ->toArray());
    }

    public static function pipelineStalls(): array
    {
        return Cache::remember('analytics:pipeline_stalls', static::TTL, function () {
            $purchaseRequests = DB::table('purchase_requests')
                ->whereNull('deleted_at')
                ->whereNull('approval_date')
                ->whereNotNull('required_by_date')
                ->where('required_by_date', '<', DB::raw('curdate()'))
                ->selectRaw("'purchase_request' as record_type, pr_number as record_number, datediff(curdate(), required_by_date) as days_overdue, id")
                ->orderBy('required_by_date', 'asc')
                ->limit(15);

            $registeredOrders = DB::table('registered_orders as ro')
                ->whereNull('ro.deleted_at')
                ->whereNotNull('ro.expected_delivery_date')
                ->where('ro.expected_delivery_date', '<', DB::raw('curdate()'))
                ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('shipments')->whereColumn('shipments.registered_order_id', 'ro.id')->whereNull('shipments.deleted_at'))
                ->selectRaw("'registered_order' as record_type, ro.ro_number as record_number, datediff(curdate(), ro.expected_delivery_date) as days_overdue, ro.id")
                ->orderBy('ro.expected_delivery_date', 'asc')
                ->limit(15);

            $payments = DB::table('payments')
                ->whereNull('deleted_at')
                ->whereNull('payment_date')
                ->whereNotNull('payment_deadline')
                ->where('payment_deadline', '<', DB::raw('curdate()'))
                ->selectRaw("'payment' as record_type, payment_no as record_number, datediff(curdate(), payment_deadline) as days_overdue, id")
                ->orderBy('payment_deadline', 'asc')
                ->limit(15);

            $shipments = DB::table('shipments')
                ->whereNull('deleted_at')
                ->whereNull('exit_date')
                ->whereNotNull('eta')
                ->where('eta', '<', DB::raw('curdate()'))
                ->selectRaw("'shipment' as record_type, shipment_no as record_number, datediff(curdate(), eta) as days_overdue, id")
                ->orderBy('eta', 'asc')
                ->limit(15);

            return DB::query()
                ->fromSub($purchaseRequests->unionAll($registeredOrders)->unionAll($payments)->unionAll($shipments), 'stalls')
                ->orderByDesc('days_overdue')
                ->limit(15)
                ->get()
                ->toArray();
        });
    }

    public static function shipmentPunctuality(): array
    {
        return Cache::remember('analytics:shipment_punctuality', static::TTL, fn() => (array)DB::selectOne('
            select
                sum(case when exit_date <= eta then 1 else 0 end) as on_time,
                sum(case when diff between 1 and 3 then 1 else 0 end) as late_1_3,
                sum(case when diff between 4 and 7 then 1 else 0 end) as late_4_7,
                sum(case when diff > 7 then 1 else 0 end) as late_8_plus,
                sum(case when exit_date is null and eta < curdate() then 1 else 0 end) as currently_overdue
            from (
                select exit_date, eta, datediff(exit_date, eta) as diff
                from shipments
                where eta is not null and deleted_at is null
            ) s
        '));
    }

    protected static function percentile(array $sortedDurations, float $percent): ?int
    {
        if (!$sortedDurations) {
            return null;
        }

        return $sortedDurations[(int)floor((count($sortedDurations) - 1) * $percent)];
    }

    protected static function supportsCte(): bool
    {
        static $supports = null;

        if ($supports !== null) {
            return $supports;
        }

        try {
            $version = (string)DB::getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $isMaria = stripos($version, 'mariadb') !== false;

            if (preg_match('/^5\.5\.5-(\d+\.\d+\.\d+)-MariaDB/i', $version, $matches)) {
                $version = $matches[1];
                $isMaria = true;
            }

            $cleanVersion = preg_replace('/[^0-9.]/', '', explode('-', $version)[0]);

            $supports = $isMaria
                ? version_compare($cleanVersion, '10.2.0', '>=')
                : version_compare($cleanVersion, '8.0.0', '>=');
        } catch (\Throwable) {
            $supports = false;
        }

        return $supports;
    }
}
