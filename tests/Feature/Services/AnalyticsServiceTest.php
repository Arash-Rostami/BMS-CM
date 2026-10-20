<?php

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Custom;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\RegisteredOrderItem;
use App\Models\Shipment;
use App\Models\Status;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    private const KEYS = ['concentration', 'cycle_time', 'exposure_aging', 'open_exposure', 'pipeline_stalls', 'shipment_punctuality'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        foreach (self::KEYS as $key) {
            Cache::forget("analytics:{$key}");
        }
        Queue::fake();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        foreach (self::KEYS as $key) {
            Cache::forget("analytics:{$key}");
        }
        parent::tearDown();
    }

    private function useMysql(): void
    {
        $env = base_path('.env');
        if (is_file($env)) {
            $vals = [];
            foreach (explode("\n", (string) file_get_contents($env)) as $line) {
                if (preg_match('/^\s*(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)$/', $line, $m)) {
                    $vals[$m[1]] = trim(preg_replace('/\s+#.*$/', '', trim($m[2])), "\"' \t");
                }
            }
            $map = ['DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_DATABASE' => 'database', 'DB_USERNAME' => 'username', 'DB_PASSWORD' => 'password'];
            foreach ($map as $envKey => $cfgKey) {
                if (isset($vals[$envKey])) {
                    config(['database.connections.mysql.'.$cfgKey => $vals[$envKey]]);
                }
            }
        }
        DB::purge('mysql');
        config(['database.default' => 'mysql']);
    }

    public function test_each_method_reads_through_its_exact_cache_key(): void
    {
        $methods = [
            'concentration' => 'concentrationRisk',
            'cycle_time' => 'cycleTimeByStage',
            'exposure_aging' => 'exposureAging',
            'open_exposure' => 'openCurrencyExposure',
            'pipeline_stalls' => 'pipelineStalls',
            'shipment_punctuality' => 'shipmentPunctuality',
        ];

        foreach ($methods as $key => $method) {
            $stub = ['stub' => $key];
            Cache::put("analytics:{$key}", $stub, now()->addMinutes(5));

            $this->assertSame($stub, AnalyticsService::{$method}(), $method);
            Cache::forget("analytics:{$key}");
        }
    }

    public function test_a_cache_miss_writes_the_exact_key(): void
    {
        AnalyticsService::exposureAging();

        $this->assertTrue(Cache::has('analytics:exposure_aging'));
    }

    public function test_concentration_risk_returns_hhi_values_within_bounds(): void
    {
        $result = AnalyticsService::concentrationRisk();

        $this->assertSame(['supplier_hhi', 'currency_hhi'], array_keys($result));
        foreach ($result as $hhi) {
            $this->assertIsFloat($hhi);
            $this->assertGreaterThanOrEqual(0, $hhi);
            $this->assertLessThanOrEqual(10000, $hhi);
        }
    }

    public function test_cycle_time_by_stage_covers_all_four_stages(): void
    {
        $result = AnalyticsService::cycleTimeByStage();

        $this->assertSame(['request_to_order', 'order_to_payment', 'payment_to_shipment', 'shipment_to_clearance'], array_keys($result));
        foreach ($result as $stage) {
            $this->assertIsInt($stage['count']);
            $this->assertGreaterThanOrEqual(0, $stage['count']);
            $this->assertArrayHasKey('p50', $stage);
            $this->assertArrayHasKey('p90', $stage);
        }
    }

    public function test_shipment_punctuality_exposes_the_delay_buckets(): void
    {
        $result = AnalyticsService::shipmentPunctuality();

        $this->assertSame(
            ['on_time', 'late_1_3', 'late_4_7', 'late_8_plus', 'currently_overdue'],
            array_keys($result)
        );
    }

    public function test_exposure_and_stall_methods_return_arrays(): void
    {
        $this->assertIsArray(AnalyticsService::openCurrencyExposure());
        $this->assertIsArray(AnalyticsService::pipelineStalls());
    }

    private function isolate(): void
    {
        foreach (['customs', 'shipments', 'payments', 'registered_orders', 'purchase_requests'] as $table) {
            DB::table($table)->whereNull('deleted_at')->update(['deleted_at' => now()]);
        }
    }

    private function statusId(string $type, string $name): int
    {
        return (int) Status::query()->firstOrCreate(['english_type' => $type, 'english_name' => $name], ['type' => $type, 'name' => $name])->id;
    }

    private function payment(array $overrides = []): Payment
    {
        return Payment::factory()->create(array_merge([
            'payment_date' => null,
            'payment_deadline' => null,
            'status_id' => $this->statusId(Payment::TYPE_PAYMENT, 'Pending'),
            'payable_amount' => 100,
            'total_amount' => 100,
            'exchange_rate' => 1000,
        ], $overrides));
    }

    private function shipment(array $overrides = []): Shipment
    {
        return Shipment::factory()->create(array_merge([
            'eta' => null,
            'exit_date' => null,
            'warehouse_date' => null,
            'status_id' => $this->statusId(Shipment::TYPE_SHIPMENT_STATUS, 'Processing'),
            'shipment_status_id' => $this->statusId(Shipment::TYPE_TRACKING_STATUS, 'In Transit'),
        ], $overrides));
    }

    private function days(int $ago): string
    {
        return now()->subDays($ago)->toDateString();
    }

    private function row(array $rows, int $currencyId): ?object
    {
        return collect($rows)->firstWhere('id', $currencyId);
    }

    public function test_concentration_converts_to_rial_and_ignores_void_payments(): void
    {
        $this->isolate();
        [$a, $b] = [Company::factory()->create(), Company::factory()->create()];
        [$usd, $eur] = [Currency::factory()->create(), Currency::factory()->create()];
        $cancelled = $this->statusId(Payment::TYPE_PAYMENT, 'Cancelled');

        $this->payment(['payee_id' => $a->id, 'currency_id' => $usd->id, 'payable_amount' => 100, 'exchange_rate' => 1000]);
        $this->payment(['payee_id' => $b->id, 'currency_id' => $eur->id, 'payable_amount' => 100, 'exchange_rate' => 2000]);
        $this->payment(['payee_id' => $b->id, 'currency_id' => $eur->id, 'payable_amount' => 9999999, 'exchange_rate' => 1, 'status_id' => $cancelled]);
        $this->payment(['payee_id' => $b->id, 'currency_id' => $eur->id, 'payable_amount' => 9999999, 'exchange_rate' => 0]);

        $result = AnalyticsService::concentrationRisk();

        $this->assertEqualsWithDelta(5555.56, $result['supplier_hhi'], 0.01);
        $this->assertEqualsWithDelta(5555.56, $result['currency_hhi'], 0.01);
    }

    public function test_concentration_is_zero_without_payments_and_ten_thousand_for_a_single_supplier(): void
    {
        $this->isolate();
        $this->assertSame(['supplier_hhi' => 0.0, 'currency_hhi' => 0.0], AnalyticsService::concentrationRisk());

        Cache::forget('analytics:concentration');
        $this->payment(['payee_id' => Company::factory()->create()->id, 'currency_id' => Currency::factory()->create()->id]);

        $this->assertEqualsWithDelta(10000, AnalyticsService::concentrationRisk()['supplier_hhi'], 0.01);
    }

    public function test_cycle_time_measures_actual_dates_through_direct_and_linked_purchase_order_payments(): void
    {
        $this->isolate();
        $order = RegisteredOrder::factory()->create(['order_date' => '2026-01-01']);
        $other = RegisteredOrder::factory()->create(['order_date' => '2026-01-01']);
        $request = PurchaseRequest::factory()->create(['approval_date' => '2025-12-22']);
        DB::table('registered_order_purchase_request')->insert(['registered_order_id' => $order->id, 'purchase_request_id' => $request->id]);

        $single = PurchaseOrder::factory()->create();
        $shared = PurchaseOrder::factory()->create();
        DB::table('registered_order_purchase_order')->insert([
            ['registered_order_id' => $order->id, 'purchase_order_id' => $single->id],
            ['registered_order_id' => $order->id, 'purchase_order_id' => $shared->id],
            ['registered_order_id' => $other->id, 'purchase_order_id' => $shared->id],
        ]);

        $this->payment(['targetable_id' => $order->id, 'payment_date' => '2026-01-02', 'status_id' => $this->statusId(Payment::TYPE_PAYMENT, 'Cancelled')]);
        $this->payment(['targetable_id' => $order->id, 'payment_date' => '2026-01-20']);
        $this->payment(['targetable_type' => PurchaseOrder::class, 'targetable_id' => $single->id, 'payment_date' => '2026-01-11']);
        $this->payment(['targetable_type' => PurchaseOrder::class, 'targetable_id' => $shared->id, 'payment_date' => '2026-01-03']);

        $live = $this->shipment(['registered_order_id' => $order->id, 'exit_date' => '2026-01-21']);
        $this->shipment(['registered_order_id' => $order->id, 'exit_date' => '2026-01-15', 'status_id' => $this->statusId(Shipment::TYPE_SHIPMENT_STATUS, 'Cancelled')]);
        Custom::factory()->create(['shipment_id' => $live->id, 'registered_order_id' => $order->id, 'clearance_date' => '2026-02-05']);

        $stages = AnalyticsService::cycleTimeByStage();

        $this->assertSame(['count' => 1, 'p50' => 10, 'p90' => 10], $stages['request_to_order']);
        $this->assertSame(['count' => 1, 'p50' => 10, 'p90' => 10], $stages['order_to_payment']);
        $this->assertSame(['count' => 1, 'p50' => 10, 'p90' => 10], $stages['payment_to_shipment']);
        $this->assertSame(['count' => 1, 'p50' => 15, 'p90' => 15], $stages['shipment_to_clearance']);
    }

    public function test_cycle_time_picks_median_and_p90_by_index_and_drops_negative_gaps(): void
    {
        $this->isolate();
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 100] as $gap) {
            $order = RegisteredOrder::factory()->create(['order_date' => '2026-01-01']);
            $this->payment(['targetable_id' => $order->id, 'payment_date' => now()->parse('2026-01-01')->addDays($gap)->toDateString()]);
        }
        $early = RegisteredOrder::factory()->create(['order_date' => '2026-03-01']);
        $this->payment(['targetable_id' => $early->id, 'payment_date' => '2026-02-01']);

        $stage = AnalyticsService::cycleTimeByStage()['order_to_payment'];

        $this->assertSame(10, $stage['count']);
        $this->assertSame(5, $stage['p50']);
        $this->assertSame(9, $stage['p90']);
    }

    public function test_punctuality_buckets_warehouse_receipt_against_eta_and_skips_arrived_and_cancelled(): void
    {
        $this->isolate();
        $order = RegisteredOrder::factory()->create();
        $delivered = $this->statusId(Shipment::TYPE_TRACKING_STATUS, 'Delivered');
        $cancelled = $this->statusId(Shipment::TYPE_SHIPMENT_STATUS, 'Cancelled');

        foreach ([['2026-01-10', '2026-01-09'], ['2026-01-10', '2026-01-12'], ['2026-01-10', '2026-01-15'], ['2026-01-10', '2026-01-25']] as [$eta, $received]) {
            $this->shipment(['registered_order_id' => $order->id, 'eta' => $eta, 'warehouse_date' => $received]);
        }
        $this->shipment(['registered_order_id' => $order->id, 'eta' => '2026-01-01']);
        $this->shipment(['registered_order_id' => $order->id, 'eta' => '2026-01-01', 'shipment_status_id' => $delivered]);
        $this->shipment(['registered_order_id' => $order->id, 'eta' => '2026-01-01', 'status_id' => $cancelled]);
        $this->shipment(['registered_order_id' => $order->id, 'eta' => null, 'warehouse_date' => '2026-01-20']);
        $this->shipment(['registered_order_id' => $order->id, 'eta' => '2999-01-01']);

        $result = AnalyticsService::shipmentPunctuality();

        $this->assertSame([1, 1, 1, 1, 1], array_map('intval', array_values($result)));
        $this->assertSame(['on_time', 'late_1_3', 'late_4_7', 'late_8_plus', 'currently_overdue'], array_keys($result));
    }

    public function test_aging_buckets_only_open_overdue_payments_per_currency(): void
    {
        $this->isolate();
        [$usd, $eur] = [Currency::factory()->create(), Currency::factory()->create()];

        $this->payment(['currency_id' => $usd->id, 'payable_amount' => 100, 'payment_deadline' => $this->days(45)]);
        $this->payment(['currency_id' => $usd->id, 'payable_amount' => 50, 'payment_deadline' => $this->days(10)]);
        $this->payment(['currency_id' => $usd->id, 'payable_amount' => 25, 'payment_deadline' => $this->days(75)]);
        $this->payment(['currency_id' => $eur->id, 'payable_amount' => 7, 'payment_deadline' => $this->days(120)]);
        $this->payment(['currency_id' => $usd->id, 'payable_amount' => 1000, 'payment_deadline' => $this->days(45), 'payment_date' => $this->days(5)]);
        $this->payment(['currency_id' => $usd->id, 'payable_amount' => 1000, 'payment_deadline' => $this->days(45), 'status_id' => $this->statusId(Payment::TYPE_PAYMENT, 'Cancelled')]);
        $this->payment(['currency_id' => $usd->id, 'payable_amount' => 1000, 'payment_deadline' => $this->days(45), 'status_id' => $this->statusId(Payment::TYPE_PAYMENT, 'Completed')]);
        $this->payment(['currency_id' => $usd->id, 'payable_amount' => 1000, 'payment_deadline' => null]);
        $this->payment(['currency_id' => $usd->id, 'payable_amount' => 1000, 'payment_deadline' => now()->addDays(5)->toDateString()]);

        $rows = AnalyticsService::exposureAging();
        $usdRow = $this->row($rows, $usd->id);
        $eurRow = $this->row($rows, $eur->id);

        $this->assertCount(2, $rows);
        $this->assertSame([50.0, 100.0, 25.0, 0.0], [(float) $usdRow->bucket_0_30, (float) $usdRow->bucket_31_60, (float) $usdRow->bucket_61_90, (float) $usdRow->bucket_90_plus]);
        $this->assertSame([0.0, 0.0, 0.0, 7.0], [(float) $eurRow->bucket_0_30, (float) $eurRow->bucket_31_60, (float) $eurRow->bucket_61_90, (float) $eurRow->bucket_90_plus]);
    }

    public function test_open_exposure_nets_only_settled_same_currency_payments_including_linked_purchase_orders(): void
    {
        $this->isolate();
        [$usd, $eur] = [Currency::factory()->create(), Currency::factory()->create()];
        $order = RegisteredOrder::factory()->create(['currency_id' => $usd->id]);
        $euroOrder = RegisteredOrder::factory()->create(['currency_id' => $eur->id]);
        RegisteredOrderItem::factory()->create(['registered_order_id' => $order->id, 'line_total' => 1000]);
        RegisteredOrderItem::factory()->create(['registered_order_id' => $euroOrder->id, 'line_total' => 200]);
        $purchaseOrder = PurchaseOrder::factory()->create();
        DB::table('registered_order_purchase_order')->insert(['registered_order_id' => $order->id, 'purchase_order_id' => $purchaseOrder->id]);

        $this->payment(['targetable_id' => $order->id, 'currency_id' => $usd->id, 'payable_amount' => 400, 'payment_date' => $this->days(3)]);
        $this->payment(['targetable_type' => PurchaseOrder::class, 'targetable_id' => $purchaseOrder->id, 'currency_id' => $usd->id, 'payable_amount' => 100, 'payment_date' => $this->days(2)]);
        $this->payment(['targetable_id' => $order->id, 'currency_id' => $usd->id, 'payable_amount' => 300]);
        $this->payment(['targetable_id' => $order->id, 'currency_id' => $eur->id, 'payable_amount' => 500, 'payment_date' => $this->days(1)]);
        $this->payment(['targetable_id' => $order->id, 'currency_id' => $usd->id, 'payable_amount' => 90, 'payment_date' => $this->days(1), 'status_id' => $this->statusId(Payment::TYPE_PAYMENT, 'Rejected')]);

        $rows = AnalyticsService::openCurrencyExposure();

        $this->assertEqualsWithDelta(500, (float) $this->row($rows, $usd->id)->open_exposure, 0.001);
        $this->assertEqualsWithDelta(200, (float) $this->row($rows, $eur->id)->open_exposure, 0.001);
    }

    public function test_open_exposure_never_goes_negative_and_drops_fully_paid_orders(): void
    {
        $this->isolate();
        $usd = Currency::factory()->create();
        $order = RegisteredOrder::factory()->create(['currency_id' => $usd->id]);
        RegisteredOrderItem::factory()->create(['registered_order_id' => $order->id, 'line_total' => 100]);
        $this->payment(['targetable_id' => $order->id, 'currency_id' => $usd->id, 'payable_amount' => 150, 'payment_date' => $this->days(1)]);

        $this->assertNull($this->row(AnalyticsService::openCurrencyExposure(), $usd->id));
    }

    public function test_stalls_list_only_live_overdue_records_ranked_by_days_overdue(): void
    {
        $this->isolate();
        $review = $this->statusId(PurchaseRequest::TYPE_PURCHASE_REQUEST, 'Under Review');
        $authorized = $this->statusId(PurchaseRequest::TYPE_PURCHASE_REQUEST, 'Authorized');
        $declined = $this->statusId(PurchaseRequest::TYPE_PURCHASE_REQUEST, 'Declined');

        $stalledRequest = PurchaseRequest::factory()->create(['approval_date' => null, 'status_id' => $review, 'required_by_date' => $this->days(20)]);
        PurchaseRequest::factory()->create(['approval_date' => null, 'status_id' => $authorized, 'required_by_date' => $this->days(30)]);
        PurchaseRequest::factory()->create(['approval_date' => null, 'status_id' => $declined, 'required_by_date' => $this->days(30)]);
        PurchaseRequest::factory()->create(['approval_date' => $this->days(40), 'status_id' => $review, 'required_by_date' => $this->days(30)]);

        $stalledOrder = RegisteredOrder::factory()->create(['expected_delivery_date' => $this->days(70)]);
        $shippedOrder = RegisteredOrder::factory()->create(['expected_delivery_date' => $this->days(70)]);
        $this->shipment(['registered_order_id' => $shippedOrder->id, 'eta' => $this->days(5), 'warehouse_date' => $this->days(1)]);

        $stalledPayment = $this->payment(['payment_deadline' => $this->days(50)]);
        $this->payment(['payment_deadline' => $this->days(60), 'status_id' => $this->statusId(Payment::TYPE_PAYMENT, 'Cancelled')]);
        $this->payment(['payment_deadline' => $this->days(60), 'payment_date' => $this->days(2)]);

        $stalledShipment = $this->shipment(['registered_order_id' => $shippedOrder->id, 'eta' => $this->days(40)]);
        $this->shipment(['registered_order_id' => $shippedOrder->id, 'eta' => $this->days(41), 'shipment_status_id' => $this->statusId(Shipment::TYPE_TRACKING_STATUS, 'Delivered')]);
        $this->shipment(['registered_order_id' => $shippedOrder->id, 'eta' => $this->days(42), 'status_id' => $this->statusId(Shipment::TYPE_SHIPMENT_STATUS, 'Cancelled')]);

        $stalls = collect(AnalyticsService::pipelineStalls());

        $this->assertSame(
            [['registered_order', 70], ['payment', 50], ['shipment', 40], ['purchase_request', 20]],
            $stalls->map(fn ($row) => [$row->record_type, (int) $row->days_overdue])->all()
        );
        $this->assertSame([$stalledOrder->ro_number, $stalledPayment->payment_no, $stalledShipment->shipment_no, $stalledRequest->pr_number], $stalls->pluck('record_number')->all());
        $this->assertSame([$stalledOrder->id, $stalledPayment->id, $stalledShipment->id, $stalledRequest->id], $stalls->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_an_order_whose_only_shipment_was_cancelled_still_counts_as_stalled_and_deleted_orders_never_feed_cycle_time(): void
    {
        $this->isolate();
        $stalled = RegisteredOrder::factory()->create(['expected_delivery_date' => $this->days(30)]);
        $this->shipment(['registered_order_id' => $stalled->id, 'status_id' => $this->statusId(Shipment::TYPE_SHIPMENT_STATUS, 'Cancelled')]);

        $this->assertSame(['registered_order'], collect(AnalyticsService::pipelineStalls())->pluck('record_type')->all());

        $gone = RegisteredOrder::factory()->create(['order_date' => '2026-01-01']);
        $this->payment(['targetable_id' => $gone->id, 'payment_date' => '2026-01-05']);
        $this->shipment(['registered_order_id' => $gone->id, 'exit_date' => '2026-01-10']);
        $gone->delete();
        Cache::forget('analytics:cycle_time');

        $stages = AnalyticsService::cycleTimeByStage();
        $this->assertSame(0, $stages['order_to_payment']['count']);
        $this->assertSame(0, $stages['payment_to_shipment']['count']);
    }

    public function test_the_stalled_shipments_match_the_punctuality_overdue_count(): void
    {
        $this->isolate();
        $order = RegisteredOrder::factory()->create(['expected_delivery_date' => null]);
        $this->shipment(['registered_order_id' => $order->id, 'eta' => $this->days(9)]);
        $this->shipment(['registered_order_id' => $order->id, 'eta' => $this->days(8), 'warehouse_date' => $this->days(1)]);

        $stalledShipments = collect(AnalyticsService::pipelineStalls())->where('record_type', 'shipment')->count();

        $this->assertSame($stalledShipments, (int) AnalyticsService::shipmentPunctuality()['currently_overdue']);
    }
}
