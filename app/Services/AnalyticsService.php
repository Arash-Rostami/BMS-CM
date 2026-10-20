<?php

namespace App\Services;

use App\Filament\Resources\Operational\ShipmentResource\Enums\TrackingStatus;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Status;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    protected const TTL = 300;

    protected const PAYMENT_VOID = ['Draft', 'Rejected', 'Cancelled', 'Refunded'];

    protected const SHIPMENT_VOID = ['Cancelled', 'Rejected'];

    protected const SHIPMENT_ARRIVED = [TrackingStatus::Delivered->value, TrackingStatus::ArrivedAtDestination->value];

    public static function concentrationRisk(): array
    {
        return Cache::remember('analytics:concentration', static::TTL, function () {
            $live = 'deleted_at is null and payable_amount is not null and exchange_rate > 0 and '.static::paymentNotVoid('payments');

            $result = DB::selectOne("
                select
                    coalesce((
                        select sum(spend * spend) / nullif(power(sum(spend), 2), 0) * 10000
                        from (
                            select sum(payable_amount * exchange_rate) as spend
                            from payments
                            where payee_id is not null and {$live}
                            group by payee_id
                        ) supplier_spend
                    ), 0) as supplier_hhi,
                    coalesce((
                        select sum(spend * spend) / nullif(power(sum(spend), 2), 0) * 10000
                        from (
                            select sum(payable_amount * exchange_rate) as spend
                            from payments
                            where currency_id is not null and {$live}
                            group by currency_id
                        ) currency_spend
                    ), 0) as currency_hhi
            ");

            return [
                'supplier_hhi' => (float) ($result->supplier_hhi ?? 0),
                'currency_hhi' => (float) ($result->currency_hhi ?? 0),
            ];
        });
    }

    public static function cycleTimeByStage(): array
    {
        return Cache::remember('analytics:cycle_time', static::TTL, function () {
            $firstPaid = '(
                select op.registered_order_id, min(op.payment_date) as first_payment_date
                from ('.static::orderPaymentsSql().') op
                join registered_orders live_order on live_order.id = op.registered_order_id and live_order.deleted_at is null
                where op.payment_date is not null and '.static::paymentNotVoid('op').'
                group by op.registered_order_id
            )';
            $liveShipment = static::shipmentLive('s');

            $rows = DB::select("
                select 'request_to_order' as stage, datediff(ro.order_date, pr.approval_date) as duration
                from purchase_requests pr
                join registered_order_purchase_request pivot on pivot.purchase_request_id = pr.id
                join registered_orders ro on ro.id = pivot.registered_order_id
                where pr.deleted_at is null and ro.deleted_at is null
                  and pr.approval_date is not null and ro.order_date is not null
                  and ro.order_date >= pr.approval_date

                union all

                select 'order_to_payment', datediff(pay.first_payment_date, ro.order_date)
                from registered_orders ro
                join {$firstPaid} pay on pay.registered_order_id = ro.id
                where ro.deleted_at is null and ro.order_date is not null
                  and pay.first_payment_date >= ro.order_date

                union all

                select 'payment_to_shipment', datediff(s.exit_date, pay.first_payment_date)
                from shipments s
                join {$firstPaid} pay on pay.registered_order_id = s.registered_order_id
                where s.deleted_at is null and {$liveShipment} and s.exit_date is not null
                  and s.exit_date >= pay.first_payment_date

                union all

                select 'shipment_to_clearance', datediff(c.clearance_date, s.exit_date)
                from customs c
                join shipments s on s.id = c.shipment_id
                where c.deleted_at is null and s.deleted_at is null and {$liveShipment}
                  and s.exit_date is not null and c.clearance_date is not null
                  and c.clearance_date >= s.exit_date

                order by stage, duration
            ");

            $stages = [
                'request_to_order' => [],
                'order_to_payment' => [],
                'payment_to_shipment' => [],
                'shipment_to_clearance' => [],
            ];

            foreach ($rows as $row) {
                $stages[$row->stage][] = (int) $row->duration;
            }

            return array_map(fn (array $durations) => [
                'count' => count($durations),
                'p50' => static::percentile($durations, 0.5),
                'p90' => static::percentile($durations, 0.9),
            ], $stages);
        });
    }

    public static function exposureAging(): array
    {
        return Cache::remember('analytics:exposure_aging', static::TTL, fn () => DB::table(function ($query) {
            $query->from('payments')
                ->select('currency_id')
                ->selectRaw('sum(case when payment_deadline >= curdate() - interval 30 day then payable_amount else 0 end) as bucket_0_30')
                ->selectRaw('sum(case when payment_deadline < curdate() - interval 30 day and payment_deadline >= curdate() - interval 60 day then payable_amount else 0 end) as bucket_31_60')
                ->selectRaw('sum(case when payment_deadline < curdate() - interval 60 day and payment_deadline >= curdate() - interval 90 day then payable_amount else 0 end) as bucket_61_90')
                ->selectRaw('sum(case when payment_deadline < curdate() - interval 90 day then payable_amount else 0 end) as bucket_90_plus')
                ->whereNull('deleted_at')
                ->whereRaw(static::paymentNotVoid('payments'))
                ->whereRaw('not '.static::paymentSettled('payments'))
                ->whereNotNull('payment_deadline')
                ->where('payment_deadline', '<', DB::raw('curdate()'))
                ->groupBy('currency_id');
        }, 'p')
            ->join('currencies as c', fn ($j) => $j->on('c.id', '=', 'p.currency_id')->whereNull('c.deleted_at'))
            ->select('c.id', 'c.name', 'c.english_name', 'p.bucket_0_30', 'p.bucket_31_60', 'p.bucket_61_90', 'p.bucket_90_plus')
            ->get()
            ->toArray());
    }

    public static function openCurrencyExposure(): array
    {
        return Cache::remember('analytics:open_exposure', static::TTL, fn () => DB::table(function ($query) {
            $items = DB::table('registered_order_items')
                ->select('registered_order_id', DB::raw('sum(line_total) as total'))
                ->whereNull('deleted_at')
                ->groupBy('registered_order_id');

            $paid = DB::table(DB::raw('('.static::orderPaymentsSql().') as op'))
                ->select('op.registered_order_id', 'op.currency_id', DB::raw('sum(op.payable_amount) as total'))
                ->whereRaw(static::paymentNotVoid('op'))
                ->whereRaw(static::paymentSettled('op'))
                ->groupBy('op.registered_order_id', 'op.currency_id');

            $query->from('registered_orders as ro')
                ->leftJoinSub($items, 'items', 'items.registered_order_id', '=', 'ro.id')
                ->leftJoinSub($paid, 'paid', fn ($join) => $join->on('paid.registered_order_id', '=', 'ro.id')->on('paid.currency_id', '=', 'ro.currency_id'))
                ->whereNull('ro.deleted_at')
                ->groupBy('ro.currency_id')
                ->select('ro.currency_id')
                ->selectRaw('sum(greatest(coalesce(items.total, 0) - coalesce(paid.total, 0), 0)) as open_exposure')
                ->havingRaw('open_exposure > 0');
        }, 'e')
            ->join('currencies as c', fn ($j) => $j->on('c.id', '=', 'e.currency_id')->whereNull('c.deleted_at'))
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
                ->where(fn ($q) => $q->whereNull('status_id')->orWhereNotIn('status_id', static::statusIds(PurchaseRequest::TYPE_PURCHASE_REQUEST, ['Authorized', 'Declined'])))
                ->whereNotNull('required_by_date')
                ->where('required_by_date', '<', DB::raw('curdate()'))
                ->selectRaw("'purchase_request' as record_type, pr_number as record_number, datediff(curdate(), required_by_date) as days_overdue, id")
                ->orderBy('required_by_date', 'asc')
                ->limit(15);

            $registeredOrders = DB::table('registered_orders as ro')
                ->whereNull('ro.deleted_at')
                ->whereNotNull('ro.expected_delivery_date')
                ->where('ro.expected_delivery_date', '<', DB::raw('curdate()'))
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('shipments')->whereColumn('shipments.registered_order_id', 'ro.id')->whereNull('shipments.deleted_at')->whereRaw(static::shipmentLive('shipments')))
                ->selectRaw("'registered_order' as record_type, ro.ro_number as record_number, datediff(curdate(), ro.expected_delivery_date) as days_overdue, ro.id")
                ->orderBy('ro.expected_delivery_date', 'asc')
                ->limit(15);

            $payments = DB::table('payments')
                ->whereNull('deleted_at')
                ->whereRaw(static::paymentNotVoid('payments'))
                ->whereRaw('not '.static::paymentSettled('payments'))
                ->whereNotNull('payment_deadline')
                ->where('payment_deadline', '<', DB::raw('curdate()'))
                ->selectRaw("'payment' as record_type, payment_no as record_number, datediff(curdate(), payment_deadline) as days_overdue, id")
                ->orderBy('payment_deadline', 'asc')
                ->limit(15);

            $shipments = DB::table('shipments as s')
                ->whereNull('s.deleted_at')
                ->whereRaw(static::shipmentLive('s'))
                ->whereRaw('not '.static::shipmentArrived('s'))
                ->whereNull('s.warehouse_date')
                ->whereNotNull('s.eta')
                ->where('s.eta', '<', DB::raw('curdate()'))
                ->selectRaw("'shipment' as record_type, s.shipment_no as record_number, datediff(curdate(), s.eta) as days_overdue, s.id")
                ->orderBy('s.eta', 'asc')
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
        return Cache::remember('analytics:shipment_punctuality', static::TTL, function () {
            $live = static::shipmentLive('s');
            $arrived = static::shipmentArrived('s');

            return (array) DB::selectOne("
                select
                    coalesce(sum(case when diff <= 0 then 1 else 0 end), 0) as on_time,
                    coalesce(sum(case when diff between 1 and 3 then 1 else 0 end), 0) as late_1_3,
                    coalesce(sum(case when diff between 4 and 7 then 1 else 0 end), 0) as late_4_7,
                    coalesce(sum(case when diff > 7 then 1 else 0 end), 0) as late_8_plus,
                    coalesce(sum(case when warehouse_date is null and eta < curdate() and not arrived then 1 else 0 end), 0) as currently_overdue
                from (
                    select s.warehouse_date, s.eta, datediff(s.warehouse_date, s.eta) as diff, {$arrived} as arrived
                    from shipments s
                    where s.eta is not null and s.deleted_at is null and {$live}
                ) t
            ");
        });
    }

    protected static function statusIds(string $type, array $names): array
    {
        return Status::query()
            ->where('english_type', $type)
            ->whereIn('english_name', $names)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    protected static function idList(array $ids): string
    {
        return $ids === [] ? '0' : implode(',', $ids);
    }

    protected static function paymentNotVoid(string $alias): string
    {
        $void = static::idList(static::statusIds(Payment::TYPE_PAYMENT, static::PAYMENT_VOID));

        return "({$alias}.status_id is null or {$alias}.status_id not in ({$void}))";
    }

    protected static function paymentSettled(string $alias): string
    {
        $completed = static::idList(static::statusIds(Payment::TYPE_PAYMENT, ['Completed']));

        return "({$alias}.payment_date is not null or coalesce({$alias}.status_id in ({$completed}), 0))";
    }

    protected static function shipmentLive(string $alias): string
    {
        $void = static::idList(static::statusIds(Shipment::TYPE_SHIPMENT_STATUS, static::SHIPMENT_VOID));

        return "({$alias}.status_id is null or {$alias}.status_id not in ({$void}))";
    }

    protected static function shipmentArrived(string $alias): string
    {
        $arrived = static::idList(static::statusIds(Shipment::TYPE_TRACKING_STATUS, static::SHIPMENT_ARRIVED));

        return "coalesce({$alias}.shipment_status_id in ({$arrived}), 0)";
    }

    protected static function orderPaymentsSql(): string
    {
        $order = DB::getPdo()->quote(RegisteredOrder::class);
        $purchaseOrder = DB::getPdo()->quote(PurchaseOrder::class);

        return "
            select p.id, p.targetable_id as registered_order_id, p.payment_date, p.payable_amount, p.currency_id, p.status_id
            from payments p
            where p.deleted_at is null and p.targetable_type = {$order}

            union all

            select p.id, link.registered_order_id, p.payment_date, p.payable_amount, p.currency_id, p.status_id
            from payments p
            join registered_order_purchase_order link on link.purchase_order_id = p.targetable_id
            where p.deleted_at is null and p.targetable_type = {$purchaseOrder}
              and (select count(*) from registered_order_purchase_order x where x.purchase_order_id = p.targetable_id) = 1
        ";
    }

    protected static function percentile(array $sortedDurations, float $percent): ?int
    {
        if (! $sortedDurations) {
            return null;
        }

        return $sortedDurations[(int) floor((count($sortedDurations) - 1) * $percent)];
    }

}
