<?php

namespace Tests\Feature\Services\Reports;

use App\Models\BankProfile;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency;
use App\Models\EntityAttribute;
use App\Models\Product;
use App\Models\RegisteredOrder;
use App\Models\RegisteredOrderItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Status;
use App\Services\Reports\Builders\ExecutiveReportService;
use App\Services\Reports\Checks\Check;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Checks\ExecutiveChecks;
use App\Services\Reports\Rules\FxRateService;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Support\TransitLedger;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExecutiveReportTest extends TestCase
{
    private Currency $eur;

    private Currency $usd;

    private Currency $gbp;

    private Company $carrier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        app()->setLocale('en');
        Currency::whereRaw('LOWER(english_name) = ?', ['euro'])->delete();
        FxRateService::flush();
        ReportStages::flush();
        TransitLedger::flush();
        $this->eur = Currency::factory()->create(['english_name' => 'Euro']);
        $this->usd = Currency::factory()->create(['english_name' => 'USD'.substr(uniqid(), -7)]);
        $this->gbp = Currency::factory()->create(['english_name' => 'GBP'.substr(uniqid(), -7)]);
        $this->carrier = Company::factory()->create();
        $this->profile($this->usd, $this->eur, 1000, 900);
    }

    protected function tearDown(): void
    {
        TransitLedger::$cap = 2000;
        TransitLedger::flush();
        DB::rollBack();
        FxRateService::flush();
        ReportStages::flush();
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

    private function service(): ExecutiveReportService
    {
        return app(ExecutiveReportService::class);
    }

    private function filters(array $extra = []): ReportFilters
    {
        return ReportFilters::fromArray(['company_id' => $this->carrier->id] + $extra);
    }

    private function statusRow(string $name, string $type): Status
    {
        $status = Status::firstOrCreate(['english_type' => $type, 'english_name' => $name], ['type' => $type, 'name' => $name]);
        ReportStages::flush();

        return $status;
    }

    private function profile(Currency $requested, Currency $purchased, float $amount, float $equivalent, string $status = 'Purchased', ?RegisteredOrder $order = null): BankProfile
    {
        return BankProfile::factory()->create([
            'status_id' => $this->statusRow($status, BankProfile::TYPE_BANK_PROFILE)->id,
            'registered_order_id' => ($order ?? RegisteredOrder::factory()->create())->id,
            'requested_currency_id' => $requested->id,
            'purchased_currency_id' => $purchased->id,
            'requested_amount' => $amount,
            'purchased_equivalent' => $equivalent,
            'purchase_date' => '2026-01-01',
        ]);
    }

    private function order(Currency $currency, float $value = 1000, ?Product $product = null, float $grossWeight = 1000): RegisteredOrder
    {
        $order = RegisteredOrder::factory()->create(['currency_id' => $currency->id]);

        RegisteredOrderItem::factory()->create([
            'registered_order_id' => $order->id,
            'product_id' => ($product ?? Product::factory()->create())->id,
            'quantity' => 100,
            'unit' => 'pcs',
            'unit_price' => $value / 100,
            'line_total' => $value,
            'gross_weight' => $grossWeight,
            'net_weight' => $grossWeight * 0.9,
        ]);

        return $order;
    }

    private function shipment(RegisteredOrder $order, array $attributes = [], ?float $grand = null, ?string $invoiceCurrency = null): Shipment
    {
        $shipment = Shipment::factory()->create($attributes + [
            'registered_order_id' => $order->id,
            'company_id' => $this->carrier->id,
            'shipment_status_id' => null,
            'status_id' => $this->statusRow('Active Test Status', Shipment::TYPE_SHIPMENT_STATUS)->id,
            'doc_status_id' => null,
            'warehouse_date' => null,
            'exit_date' => today()->subDays(5)->toDateString(),
            'etd' => null,
            'eta' => today()->addDays(30)->toDateString(),
            'shipped_quantity' => null,
            'grand_total' => $grand,
            'bl_number' => 'BL-'.uniqid(),
        ]);

        if ($invoiceCurrency !== null) {
            EntityAttribute::factory()->forEntity($shipment)->create(['key' => 'commercial_invoice', 'value' => ['currency' => $invoiceCurrency]]);
        }

        return $shipment;
    }

    private function item(Shipment $shipment, ?Product $product, float $total, array $extra = []): ShipmentItem
    {
        return ShipmentItem::factory()->create($extra + [
            'shipment_id' => $shipment->id,
            'product_id' => $product?->id,
            'name' => null,
            'english_name' => 'Item '.uniqid(),
            'quantity' => 10,
            'unit' => 'kg',
            'unit_price' => $total / 10,
            'total_amount' => $total,
            'gross_weight' => 100,
            'net_weight' => 90,
        ]);
    }

    private function mix(): array
    {
        $root = Category::factory()->create(['parent_id' => null, 'level' => 0]);
        $child = Category::factory()->forParent($root)->create();
        $other = Category::factory()->create(['parent_id' => null, 'level' => 0]);
        $p1 = Product::factory()->create(['category_id' => $child->id]);
        $p2 = Product::factory()->create(['category_id' => $other->id]);
        $p3 = Product::factory()->create(['category_id' => $other->id]);
        $s = [];

        $s[1] = $this->shipment($this->order($this->usd, 10000), ['eta' => today()->subDay()->toDateString()], 1000, $this->usd->english_name);
        $this->item($s[1], $p1, 800, ['gross_weight' => 2000]);
        $this->item($s[1], null, 100, ['english_name' => 'Loose', 'gross_weight' => 0]);

        $s[2] = $this->shipment($this->order($this->eur, 4000), ['eta' => null], 500, 'Euro');
        $this->item($s[2], $p2, 400, ['gross_weight' => 1000]);
        $this->item($s[2], null, 100, ['english_name' => 'loose', 'gross_weight' => 0]);

        $s[3] = $this->shipment($this->order($this->eur, 5000, $p3), ['shipped_quantity' => 40]);

        $s[4] = $this->shipment($this->order($this->eur), [], 400, 'Euro');
        $this->item($s[4], $p2, 100);

        $s[5] = $this->shipment($this->order($this->eur), [], 100, $this->usd->english_name);
        $this->item($s[5], $p2, 100);

        $s[6] = $this->shipment($this->order($this->gbp), [], 700, $this->gbp->english_name);
        $this->item($s[6], $p2, 700);

        $s[7] = $this->shipment($this->order($this->eur), [], 100, 'ZZZ unknown');
        $this->item($s[7], $p2, 100);

        $noValue = $this->order($this->eur);
        $s[8] = $this->shipment($noValue);
        $s[9] = $this->shipment($noValue);

        TransitLedger::flush();

        return [$s, compact('root', 'other', 'p1', 'p2', 'p3')];
    }

    private function lines(?ReportFilters $filters = null): array
    {
        return collect($this->service()->summary($filters ?? $this->filters()))->mapWithKeys(fn (array $card): array => [$card['key'] => $card['lines']])->all();
    }

    private function byLabel(array $lines, string $key, array $replace = []): string
    {
        $label = __('resources/report/strings.executive.lines.'.$key, $replace);

        return collect($lines)->firstWhere('label', $label)['value'] ?? throw new \RuntimeException("line {$key} missing");
    }

    private function quickValues(?ReportFilters $filters = null): array
    {
        return collect($this->lines($filters))->flatten(1)->filter(fn (array $line): bool => isset($line['quick']))->pluck('value', 'quick')->all();
    }

    private function row(Shipment $shipment, ?ReportFilters $filters = null): array
    {
        return TransitLedger::rows($filters ?? $this->filters())['rows'][$shipment->id];
    }

    public function test_cards_match_hand_computed_numbers(): void
    {
        [$s] = $this->mix();
        $lines = $this->lines();

        $this->assertCount(4, $this->service()->summary($this->filters()));
        $this->assertSame('9', $this->byLabel($lines['cargo'], 'shipments'));
        $this->assertSame('1', $this->byLabel($lines['cargo'], 'delayed'));
        $this->assertSame('1', $this->byLabel($lines['cargo'], 'no_eta'));
        $this->assertSame('2', $this->byLabel($lines['cargo'], 'weight_unknown'));
        $this->assertSame('3,500.00', $this->byLabel($lines['value'], 'eur_total'));
        $this->assertSame('4', $this->byLabel($lines['value'], 'counted'));
        $this->assertSame('4', $this->byLabel($lines['value'], 'excluded_data'));
        $this->assertSame('1', $this->byLabel($lines['value'], 'excluded_no_rate'));
        $this->assertSame('1', $this->byLabel($lines['value'], 'estimated'));
        $this->assertSame('57.1%', $this->byLabel($lines['value'], 'estimated_share'));
        $this->assertSame('1,000.00', collect($lines['value'])->firstWhere('label', $this->usd->english_name)['value']);
        $this->assertSame('2,600.00', collect($lines['value'])->firstWhere('label', 'Euro')['value']);
        $this->assertSame('700.00', $this->byLabel($lines['value'], 'excluded_currency', ['currency' => $this->gbp->english_name]));
        $this->assertEqualsWithDelta(900.0, $this->row($s[1])['eur'], 0.005);
        $this->assertEqualsWithDelta(2000.0, $this->row($s[3])['eur'], 0.005);
        $this->assertTrue($this->row($s[3])['estimated']);
    }

    public function test_identity_and_every_quick_line_equal_the_filtered_row_count(): void
    {
        $this->mix();
        $values = $this->quickValues();

        $this->assertSame(9, (int) $values['counted'] + (int) $values['excluded_data'] + (int) $values['excluded_no_rate']);
        $this->assertSame(9, (int) $values['supply_full'] + (int) $values['supply_partial'] + (int) $values['supply_none'] + (int) $values['supply_allocated'] + (int) $values['supply_unknown']);

        foreach ($values as $quick => $value) {
            $this->assertSame((int) $value, $this->service()->query($this->filters(['quick' => $quick]))->get()->count(), $quick);
        }

        $this->assertCount(9, $this->service()->query($this->filters(['quick' => 'nope']))->get());

        foreach (collect($this->lines())->flatten(1)->reject(fn (array $line): bool => isset($line['quick'])) as $line) {
            $this->assertArrayNotHasKey('quick', $line);
        }
    }

    public function test_reasons_flags_and_toggles(): void
    {
        [$s] = $this->mix();

        $this->assertSame([0, 0, 0, 4, 3, 5, 0, 1, 1], array_map(fn (Shipment $shipment): int => $this->row($shipment)['reason'], array_values($s)));
        $this->assertSame(TransitLedger::FLAG_NO_RATE, $this->row($s[6])['flags']);
        $this->assertSame(TransitLedger::FLAG_CURRENCY_INFERRED, $this->row($s[7])['flags']);
        $this->assertEqualsWithDelta(100.0, $this->row($s[7])['eur'], 0.005);

        $with = $this->filters(['include_outliers' => true, 'include_flagged_invoices' => true]);
        $this->assertSame(0, $this->row($s[4], $with)['reason']);
        $this->assertSame(0, $this->row($s[5], $with)['reason']);
        $this->assertEqualsWithDelta(90.0, $this->row($s[5], $with)['eur'], 0.005);
        $this->assertEqualsWithDelta(400.0, $this->row($s[4], $with)['eur'], 0.005);
        $this->assertSame(0, $this->row($s[7], $with)['reason']);
    }

    public function test_an_unreadable_invoice_currency_falls_back_to_the_order_currency_visibly(): void
    {
        $order = $this->order($this->eur);
        $shipment = $this->shipment($order, [], 250, 'ZZZ unknown');
        $orderless = $this->shipment($this->order($this->eur), [], 80);
        TransitLedger::flush();
        $row = $this->row($shipment);

        $this->assertSame(0, $row['reason']);
        $this->assertSame($this->eur->id, $row['currency_id']);
        $this->assertSame(TransitLedger::FLAG_CURRENCY_INFERRED, $row['flags']);
        $this->assertEqualsWithDelta(250.0, $row['eur'], 0.005);
        $this->assertSame(TransitLedger::FLAG_CURRENCY_INFERRED, $this->row($orderless)['flags']);

        $check = collect(ExecutiveChecks::all())->firstWhere('key', 'shipment.transit_currency_inferred');
        $rows = app(CheckRegistry::class)->evaluate($check, $this->filters());

        $this->assertEqualsCanonicalizing([$shipment->id, $orderless->id], $rows->pluck('id')->all());
        $this->assertSame('info', $check->severity);
        $this->assertSame('_inv_currency', $check->fix['field']);
        $this->assertSame('Euro', $rows->firstWhere('id', $shipment->id)['offending']);
    }

    public function test_a_cancelled_sibling_does_not_stop_a_single_shipment_order_from_using_the_whole_order_value(): void
    {
        $order = $this->order($this->eur, 1000);
        $live = $this->shipment($order);
        $this->shipment($order, ['status_id' => $this->statusRow('Cancelled', Shipment::TYPE_SHIPMENT_STATUS)->id]);
        TransitLedger::flush();
        $row = $this->row($live);

        $this->assertTrue($row['estimated']);
        $this->assertSame(0, $row['reason']);
        $this->assertEqualsWithDelta(1000.0, $row['eur'], 0.005);
    }

    public function test_shipped_quantities_above_the_order_quantity_are_listed(): void
    {
        $over = $this->order($this->eur, 1000);
        $first = $this->shipment($over, ['shipped_quantity' => 100], 100, 'Euro');
        $second = $this->shipment($over, ['shipped_quantity' => 100], 100, 'Euro');
        $fine = $this->order($this->eur, 1000);
        $part = $this->shipment($fine, ['shipped_quantity' => 60], 100, 'Euro');
        $rest = $this->shipment($fine, ['shipped_quantity' => 40], 100, 'Euro');
        TransitLedger::flush();
        $check = collect(ExecutiveChecks::all())->firstWhere('key', 'shipment.transit_shipped_above_order');
        $ids = app(CheckRegistry::class)->evaluate($check, $this->filters())->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $ids);
        $this->assertNotContains($part->id, $ids);
        $this->assertNotContains($rest->id, $ids);
        $this->assertSame('shipped_quantity', $check->fix['field']);
        $this->assertSame('info', $check->severity);
    }

    public function test_the_weight_unknown_line_and_its_quick_filter_agree(): void
    {
        [$s] = $this->mix();
        $quick = $this->service()->query($this->filters(['quick' => 'weight_unknown']))->pluck('shipments.id')->all();

        $this->assertEqualsCanonicalizing([$s[8]->id, $s[9]->id], $quick);
        $this->assertCount(4, $this->service()->summary($this->filters()));
        $this->assertSame((string) count($quick), $this->quickValues()['weight_unknown']);
    }

    public function test_currency_names_are_memoised_for_the_whole_request(): void
    {
        $this->shipment($this->order($this->eur), [], 100, 'Euro');
        app(ExecutiveReportService::class)->currencyNames();
        DB::enableQueryLog();
        DB::flushQueryLog();

        foreach (range(1, 50) as $ignored) {
            app(ExecutiveReportService::class)->currencyNames();
        }

        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_check_strings_and_cache_follow_the_calendar(): void
    {
        $order = $this->order($this->eur);
        $shipment = $this->shipment($order, ['etd' => '2025-03-25', 'eta' => '2025-03-20'], 100, 'Euro');
        $check = collect(app(CheckRegistry::class)->all())->firstWhere('key', 'shipment.eta_before_etd');
        $registry = app(CheckRegistry::class);

        session(['calendar_type' => 'gregorian']);
        $gregorian = $registry->evaluate($check, $this->filters())->firstWhere('id', $shipment->id)['offending'];

        session(['calendar_type' => 'jalali']);
        $jalali = $registry->evaluate($check, $this->filters())->firstWhere('id', $shipment->id)['offending'];

        $this->assertStringContainsString(toGregorianDate('2025-03-20'), $gregorian);
        $this->assertStringContainsString(toPersianDate('2025-03-20'), $jalali);
        $this->assertNotSame($gregorian, $jalali);
    }

    public function test_the_outlier_boundary_is_strictly_above_the_factor(): void
    {
        $orders = [$this->order($this->eur), $this->order($this->eur), $this->order($this->eur)];
        $shipments = [];

        foreach ([300, 301, 200000000000] as $index => $grand) {
            $shipments[$index] = $this->shipment($orders[$index], [], $grand, 'Euro');
            $this->item($shipments[$index], null, 100);
        }

        TransitLedger::flush();

        $this->assertSame([0, 4, 4], array_map(fn (Shipment $shipment): int => $this->row($shipment)['reason'], $shipments));
        $this->assertSame(0, $this->row($shipments[2], $this->filters(['include_outliers' => true]))['reason']);
        $this->assertEqualsWithDelta(200000000000.0, $this->row($shipments[2], $this->filters(['include_outliers' => true]))['eur'], 0.005);
    }

    public function test_a_single_shipment_order_uses_the_whole_order_value_and_two_shipments_have_no_value(): void
    {
        $order = $this->order($this->eur, 1000);
        $first = $this->shipment($order);
        $row = $this->row($first);

        $this->assertTrue($row['estimated']);
        $this->assertSame(0, $row['reason']);
        $this->assertEqualsWithDelta(1000.0, $row['eur'], 0.005);

        $second = $this->shipment($order);
        TransitLedger::flush();
        $check = collect(ExecutiveChecks::all())->firstWhere('key', 'shipment.transit_value_unknown');
        $ids = app(CheckRegistry::class)->evaluate($check, $this->filters())->pluck('id')->all();

        $this->assertSame([1, 1], [$this->row($first)['reason'], $this->row($second)['reason']]);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $ids);
    }

    public function test_mixed_units_make_the_shipped_share_unknown(): void
    {
        $order = $this->order($this->eur, 1000);
        RegisteredOrderItem::factory()->create(['registered_order_id' => $order->id, 'product_id' => Product::factory()->create()->id, 'quantity' => 100, 'unit' => 'kg', 'unit_price' => 10, 'line_total' => 1000]);
        $one = $this->shipment($order, ['shipped_quantity' => 50]);
        $two = $this->shipment($order, ['shipped_quantity' => 50]);
        TransitLedger::flush();

        $this->assertSame([1, 1], [$this->row($one)['reason'], $this->row($two)['reason']]);
    }

    public function test_a_currency_without_a_rate_is_excluded_and_listed_by_the_check(): void
    {
        [$s] = $this->mix();
        $check = collect(ExecutiveChecks::all())->firstWhere('key', 'shipment.transit_fx_rate_missing');
        $rows = app(CheckRegistry::class)->evaluate($check, $this->filters());

        $this->assertSame([$s[6]->id], $rows->pluck('id')->all());
        $this->assertNull($this->row($s[6])['eur']);
    }

    public function test_the_checks_list_every_flagged_shipment(): void
    {
        [$s] = $this->mix();
        $expected = [
            'shipment.transit_value_unknown' => [$s[8]->id, $s[9]->id],
            'shipment.transit_currency_unknown' => [],
            'shipment.transit_currency_inferred' => [$s[7]->id],
            'shipment.transit_shipped_above_order' => [],
            'shipment.transit_invoice_currency_mismatch' => [$s[5]->id],
            'shipment.transit_invoice_total_outlier' => [$s[4]->id],
            'shipment.transit_value_estimated' => [$s[3]->id],
            'shipment.transit_fx_rate_missing' => [$s[6]->id],
        ];

        foreach (ExecutiveChecks::all() as $check) {
            if (isset($expected[$check->key])) {
                $this->assertEqualsCanonicalizing($expected[$check->key], app(CheckRegistry::class)->evaluate($check, $this->filters())->pluck('id')->all(), $check->key);
            }
        }

        $this->assertCount(10, ExecutiveChecks::all());
        foreach (ExecutiveChecks::all() as $check) {
            $this->assertInstanceOf(Check::class, $check);
            $this->assertSame(['executive'], $check->reports);
        }

        $all = app(CheckRegistry::class)->all();
        $this->assertSame($all->count(), $all->unique('key')->count());
    }

    public function test_supply_states_percent_and_exposure(): void
    {
        $states = [];

        foreach (['full', 'partial', 'allocated', 'unknown', 'none'] as $name) {
            $order = $this->order($this->usd, 10000);
            $states[$name] = $this->shipment($order, [], 1000, $this->usd->english_name);
            $this->item($states[$name], null, 1000);
        }

        $this->profile($this->usd, $this->usd, 1, 9950, 'Purchased', $states['full']->registeredOrder);
        $this->profile($this->usd, $this->usd, 1, 9900, 'Purchased', $states['partial']->registeredOrder);
        $this->profile($this->usd, $this->usd, 1, 5000, 'Allocated', $states['allocated']->registeredOrder);
        $this->profile(Currency::factory()->create(), $this->gbp, 1, 5000, 'Purchased', $states['unknown']->registeredOrder);
        TransitLedger::flush();

        $this->assertSame(['full' => 0, 'partial' => 1, 'allocated' => 3, 'unknown' => 4, 'none' => 2], array_map(fn (Shipment $shipment): int => $this->row($shipment)['supply'], $states));
        $this->assertEqualsWithDelta(0.995, $this->row($states['full'])['ratio'], 0.000001);
        $this->assertEqualsWithDelta(0.99, $this->row($states['partial'])['ratio'], 0.000001);

        $lines = $this->lines()['cover'];
        $this->assertSame('20.0%', $this->byLabel($lines, 'supplied_percent'));
        $this->assertSame('1,813.50', $this->byLabel($lines, 'exposure'));

        $order = $states['unknown']->registeredOrder;
        $check = collect(ExecutiveChecks::all())->firstWhere('key', 'registered_order.transit_supply_unknown');
        $this->assertSame([$order->id], app(CheckRegistry::class)->evaluate($check, $this->filters())->pluck('id')->all());
    }

    public function test_a_purchased_profile_without_an_equivalent_is_listed(): void
    {
        $order = $this->order($this->usd, 10000);
        $this->shipment($order, [], 1000, $this->usd->english_name);
        $profile = $this->profile($this->usd, $this->usd, 1, 1, 'Purchased', $order);
        $profile->forceFill(['purchased_equivalent' => null])->save();
        TransitLedger::flush();
        $check = collect(ExecutiveChecks::all())->firstWhere('key', 'bank_profile.transit_supplied_without_equivalent');

        $this->assertSame([$profile->id], app(CheckRegistry::class)->evaluate($check, $this->filters())->pluck('id')->all());
    }

    public function test_the_population_follows_the_in_transit_definition(): void
    {
        $order = $this->order($this->eur);
        $inTransit = $this->statusRow('In Transit', Shipment::TYPE_TRACKING_STATUS);
        $arrived = $this->statusRow('Arrived At Destination', Shipment::TYPE_TRACKING_STATUS);
        $cancelled = $this->statusRow('Cancelled', Shipment::TYPE_SHIPMENT_STATUS);
        $make = fn (array $attributes): Shipment => $this->shipment($order, $attributes, 100, 'Euro');
        $today = $make(['eta' => today()->toDateString()]);
        $yesterday = $make(['eta' => today()->subDay()->toDateString()]);
        $make(['warehouse_date' => today()->toDateString()]);
        $make(['shipment_status_id' => $arrived->id, 'exit_date' => null]);
        $void = $make(['status_id' => $cancelled->id]);
        $tracking = $make(['shipment_status_id' => $inTransit->id, 'exit_date' => null]);
        $etd = $make(['exit_date' => null, 'etd' => today()->toDateString()]);

        $ids = fn (array $extra = []): array => $this->service()->query($this->filters($extra))->get()->modelKeys();

        $this->assertEqualsCanonicalizing([$today->id, $yesterday->id, $tracking->id], $ids());
        $this->assertEqualsCanonicalizing([$today->id, $yesterday->id, $tracking->id, $void->id], $ids(['include_void' => true]));
        $this->assertSame([$tracking->id], $ids(['departure_basis' => 'tracking_only']));
        $this->assertContains($etd->id, $ids(['departure_basis' => 'etd_too']));
        $this->assertSame([$yesterday->id], $this->service()->query($this->filters(['quick' => 'delayed']))->get()->modelKeys());
        $this->assertSame('1', $this->byLabel($this->lines()['cargo'], 'delayed'));
    }

    public function test_the_two_charts_add_up_to_the_euro_total(): void
    {
        [$s, $m] = $this->mix();
        $category = $this->service()->chartByCategory($this->filters());
        $eta = $this->service()->chartByEta($this->filters());

        $this->assertEqualsWithDelta(3500.0, collect($category['datasets'])->sum(fn (array $dataset): float => array_sum($dataset['data'])), 0.005);
        $this->assertCount(1, $eta['datasets']);
        $this->assertCount(5, $eta['labels']);
        $this->assertCount(5, $eta['datasets'][0]['data']);
        $this->assertEqualsWithDelta(3500.0, array_sum($eta['datasets'][0]['data']), 0.005);
        $this->assertContains(__('resources/report/strings.executive.charts.bars.charges'), $category['labels']);
        $this->assertContains(__('resources/report/strings.executive.charts.bars.no_product'), $category['labels']);
        $this->assertContains($m['root']->english_name, $category['labels']);
        $this->assertNotContains($m['p1']->english_name, $category['labels']);
        $noProduct = array_search(__('resources/report/strings.executive.charts.bars.no_product'), $category['labels'], true);
        $this->assertEqualsWithDelta(190.0, $category['datasets'][2]['data'][$noProduct], 0.005);
        $this->assertEqualsWithDelta([900.0, 0.0, 0.0, 2100.0, 500.0], $eta['datasets'][0]['data'], 0.005);
        $this->assertSame([$this->gbp->english_name => 700.0], $eta['excluded']);
        $this->assertSame(4, $eta['excluded_data']);
    }

    public function test_eta_bucket_edges(): void
    {
        $order = $this->order($this->eur);

        foreach ([7, 8, 14, 15] as $days) {
            $this->shipment($order, ['eta' => today()->addDays($days)->toDateString()], 100, 'Euro');
        }

        TransitLedger::flush();
        $data = $this->service()->chartByEta($this->filters())['datasets'][0]['data'];

        $this->assertEqualsWithDelta([0.0, 100.0, 200.0, 100.0, 0.0], $data, 0.005);
    }

    public function test_top_suppliers_add_up_with_others_to_the_euro_total(): void
    {
        foreach ([900, 500, 100] as $amount) {
            $this->shipment($this->order($this->eur), [], $amount, 'Euro');
        }

        $lines = $this->lines($this->filters(['th_top_n' => 2]))['suppliers'];

        $this->assertCount(3, $lines);
        $this->assertSame('900.00', $lines[0]['value']);
        $this->assertSame('500.00', $lines[1]['value']);
        $this->assertSame(__('resources/report/strings.executive.lines.supplier', ['name' => __('resources/report/strings.executive.lines.others'), 'share' => '6.7%', 'count' => 1]), $lines[2]['label']);
        $this->assertEqualsWithDelta(1500.0, collect($lines)->sum(fn (array $line): float => (float) str_replace(',', '', $line['value'])), 0.005);
        $this->assertStringContainsString('60.0%', $lines[0]['label']);
    }

    public function test_the_product_view_adds_up_with_the_charges_row_and_groups_null_products_by_name(): void
    {
        [$s, $m] = $this->mix();
        $lines = $this->service()->productRows($this->filters(['view_mode' => 'product']));
        $footer = $lines->last();
        $loose = $lines->firstWhere('no_product', true);

        $this->assertTrue($footer['footer']);
        $this->assertEqualsWithDelta(90.0, $footer['eur'], 0.005);
        $this->assertEqualsWithDelta(190.0, $loose['eur'], 0.005);
        $this->assertSame(2, $loose['shipments']);
        $this->assertEqualsWithDelta(3500.0, $lines->sum('eur'), 0.005);
        $this->assertSame(1, $lines->where('no_product', true)->count());
        $this->assertEqualsWithDelta(500.0, $lines->firstWhere('key', 'p'.$m['p2']->id)['eur'], 0.005);
        $this->assertEqualsWithDelta(2000.0, $lines->firstWhere('key', 'p'.$m['p3']->id)['eur'], 0.005);
    }

    public function test_the_csv_rows_and_labels_match_both_views(): void
    {
        $this->mix();
        $shipments = $this->filters();
        $products = $this->filters(['view_mode' => 'product']);

        $rows = collect($this->service()->rows($shipments));
        $this->assertCount($this->service()->query($shipments)->count(), $rows);
        $this->assertSame(array_keys($this->service()->exportLabelsFor($shipments)), array_keys($rows->first()));
        $this->assertSame($this->service()->exportLabels(), $this->service()->exportLabelsFor($shipments));

        $productRows = collect($this->service()->rows($products));
        $this->assertSame(array_keys($this->service()->exportLabelsFor($products)), array_keys($productRows->first()));
        $this->assertCount($this->service()->productRows($products)->count(), $productRows);
        $this->assertNotSame($this->service()->exportLabelsFor($shipments), $this->service()->exportLabelsFor($products));
    }

    public function test_more_shipments_than_the_cap_show_a_notice_and_no_checks(): void
    {
        $order = $this->order($this->eur);
        $this->shipment($order, [], 100, 'Euro');
        $this->shipment($order, [], 100, 'Euro');
        TransitLedger::$cap = 1;
        TransitLedger::flush();
        $lines = $this->lines();

        $this->assertTrue(TransitLedger::rows($this->filters())['over']);
        $this->assertSame(__('resources/report/strings.executive.lines.over_cap', ['max' => 1]), $lines['value'][0]['label']);
        $this->assertSame(__('resources/report/strings.executive.lines.over_cap', ['max' => 1]), $lines['cover'][0]['label']);
        $this->assertSame('2', $this->byLabel($lines['cargo'], 'shipments'));
        $check = collect(ExecutiveChecks::all())->firstWhere('key', 'shipment.transit_value_estimated');
        $this->assertCount(0, app(CheckRegistry::class)->evaluate($check, $this->filters()));
    }

    public function test_the_ledger_query_count_is_constant(): void
    {
        $count = function (int $shipments): int {
            for ($i = 0; $i < $shipments; $i++) {
                $this->shipment($this->order($this->usd), [], 100, $this->usd->english_name);
            }

            TransitLedger::flush();
            FxRateService::flush();
            ReportStages::flush();
            DB::enableQueryLog();
            DB::flushQueryLog();
            TransitLedger::rows($this->filters());
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $small = $count(5);
        $this->assertSame($small, $count(45));
    }

    public function test_drill_down_urls_need_the_target_pages_and_their_permissions(): void
    {
        $this->mix();

        foreach ($this->lines()['cargo'] as $line) {
            $this->assertArrayNotHasKey('url', $line);
        }

        foreach ($this->lines()['cover'] as $line) {
            $this->assertArrayNotHasKey('url', $line);
        }
    }
}
