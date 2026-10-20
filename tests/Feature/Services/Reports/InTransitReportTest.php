<?php

namespace Tests\Feature\Services\Reports;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\RegisteredOrder;
use App\Models\RegisteredOrderItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Status;
use App\Models\StatusHistory;
use App\Services\Reports\Builders\InTransitReportService;
use App\Services\Reports\Checks\Check;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Checks\InTransitChecks;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\ReportFilters;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InTransitReportTest extends TestCase
{
    private RegisteredOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        ReportStages::flush();
        app()->setLocale('en');
        $this->order = RegisteredOrder::factory()->create();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
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

    public static function keys(): array
    {
        return array_map(fn (Check $check): array => [$check->key], InTransitChecks::all());
    }

    private function service(): InTransitReportService
    {
        return app(InTransitReportService::class);
    }

    private function filters(array $extra = []): ReportFilters
    {
        return ReportFilters::fromArray(['registered_order_id' => $this->order->id] + $extra);
    }

    private function statusRow(string $name, string $type = Shipment::TYPE_TRACKING_STATUS): Status
    {
        return Status::firstOrCreate(['english_type' => $type, 'english_name' => $name], ['type' => $type, 'name' => $name]);
    }

    private function days(?int $offset): ?string
    {
        return $offset === null ? null : today()->addDays($offset)->toDateString();
    }

    private function shipment(array $extra = [], bool $withItem = true): Shipment
    {
        $shipment = Shipment::factory()->create($extra + [
            'registered_order_id' => $this->order->id,
            'shipment_status_id' => null,
            'status_id' => $this->statusRow('Active Test Status', Shipment::TYPE_SHIPMENT_STATUS)->id,
            'doc_status_id' => null,
            'warehouse_date' => null,
            'exit_date' => null,
            'etd' => null,
            'eta' => null,
            'bl_number' => 'BL-'.uniqid(),
        ]);

        if ($withItem) {
            ShipmentItem::factory()->create(['shipment_id' => $shipment->id, 'gross_weight' => 100]);
        }

        return $shipment;
    }

    private function mix(): array
    {
        $inTransit = $this->statusRow('In Transit')->id;
        $arrived = $this->statusRow('Arrived At Destination')->id;
        $cancelled = $this->statusRow('Cancelled', Shipment::TYPE_SHIPMENT_STATUS)->id;

        return [
            'a' => $this->shipment(),
            'b' => $this->shipment(['eta' => $this->days(20)]),
            'c' => $this->shipment(['exit_date' => $this->days(-5), 'eta' => $this->days(5)]),
            'd' => $this->shipment(['shipment_status_id' => $inTransit]),
            'e' => $this->shipment(['exit_date' => $this->days(-5), 'eta' => $this->days(10)]),
            'f' => $this->shipment(['exit_date' => $this->days(-40), 'eta' => $this->days(-20)]),
            'g' => $this->shipment(['eta' => $this->days(-3)]),
            'h' => $this->shipment(['warehouse_date' => $this->days(-1)]),
            'i' => $this->shipment(['shipment_status_id' => $arrived, 'eta' => $this->days(-30)]),
            'j' => $this->shipment(['status_id' => $cancelled, 'exit_date' => $this->days(-5), 'eta' => $this->days(5)]),
        ];
    }

    private function values(?ReportFilters $filters = null): array
    {
        return collect($this->service()->summary($filters ?? $this->filters()))
            ->flatMap(fn (array $card): array => $card['lines'])
            ->filter(fn (array $line): bool => isset($line['quick']))
            ->pluck('value', 'quick')
            ->all();
    }

    private function ids(array $extra = []): array
    {
        return $this->service()->query($this->filters($extra))->get()->modelKeys();
    }

    public function test_an_explicit_arrived_tracking_status_lists_arrived_shipments_while_show_arrived_is_off(): void
    {
        $shipments = $this->mix();
        $arrived = $this->statusRow('Arrived At Destination')->id;

        $this->assertNotContains($shipments['i']->id, $this->ids());
        $this->assertSame([$shipments['i']->id], $this->ids(['status_id' => [$arrived]]));
        $this->assertSame([$shipments['i']->id], $this->service()->query($this->filters(['status_id' => [$arrived], 'show_arrived' => false]))->get()->modelKeys());
        $this->assertNotContains($shipments['i']->id, $this->ids(['status_id' => [$this->statusRow('In Transit')->id]]));
    }

    public function test_the_in_transit_delayed_quick_key_is_in_transit_and_delayed_only(): void
    {
        $shipments = $this->mix();

        $this->assertSame([$shipments['f']->id], $this->ids(['quick' => 'in_transit_delayed']));
        $this->assertContains($shipments['g']->id, $this->ids(['quick' => 'delayed']));
        $this->assertNotContains($shipments['g']->id, $this->ids(['quick' => 'in_transit_delayed']));
    }

    public function test_cards_match_an_independent_expectation(): void
    {
        $this->mix();

        $cards = collect($this->service()->summary($this->filters()));

        $this->assertSame(['awaiting', 'in_transit', 'arriving', 'delayed'], $cards->pluck('key')->all());
        $this->assertSame([
            'awaiting' => '3', 'awaiting_no_eta' => '1', 'in_transit' => '4', 'in_transit_no_eta' => '1', 'arrived_unconfirmed' => '1',
            'arriving_week' => '1', 'arriving_two_weeks' => '1', 'delayed' => '2', 'delayed_14' => '1',
        ], $this->values());
        $this->assertSame('11.5', $cards->firstWhere('key', 'delayed')['lines'][2]['value']);
        $this->assertArrayNotHasKey('quick', $cards->firstWhere('key', 'delayed')['lines'][2]);
    }

    public function test_every_quick_line_equals_its_filtered_row_count_with_and_without_arrived(): void
    {
        $this->mix();

        foreach ([false, true] as $showArrived) {
            foreach ($this->values($this->filters(['show_arrived' => $showArrived])) as $quick => $value) {
                $rows = $this->service()->query($this->filters(['quick' => $quick, 'show_arrived' => $showArrived]))->get();

                $this->assertSame((int) $value, $rows->count(), $quick);
            }
        }
    }

    public function test_the_table_base_show_arrived_and_unknown_quick_keys(): void
    {
        $mix = $this->mix();

        $this->assertEqualsCanonicalizing(collect($mix)->only(['a', 'b', 'c', 'd', 'e', 'f', 'g'])->pluck('id')->all(), $this->ids());
        $this->assertCount(9, $this->ids(['show_arrived' => true]));
        $this->assertNotContains($mix['j']->id, $this->ids(['show_arrived' => true]));
        $this->assertEqualsCanonicalizing($this->ids(), $this->ids(['quick' => 'nope']));
        $this->assertSame('arrived', $this->service()->query($this->filters(['quick' => 'arrived_unconfirmed']))->first()->state);
    }

    public function test_row_state_days_to_eta_and_weight(): void
    {
        $mix = $this->mix();

        $rows = $this->service()->query($this->filters(['show_arrived' => true]))->get()->keyBy('id');

        $this->assertSame('awaiting', $rows[$mix['a']->id]->state);
        $this->assertSame('in_transit', $rows[$mix['d']->id]->state);
        $this->assertSame('arrived', $rows[$mix['h']->id]->state);
        $this->assertNull($rows[$mix['a']->id]->days_to_eta);
        $this->assertSame(5, (int) $rows[$mix['c']->id]->days_to_eta);
        $this->assertSame(100.0, (float) $rows[$mix['c']->id]->total_weight);
    }

    public function test_charts_reconcile_with_the_table_and_the_cards(): void
    {
        $this->mix();

        $this->assertSame(7, array_sum($this->service()->chartStages($this->filters())['counts']));
        $this->assertSame(9, array_sum($this->service()->chartStages($this->filters(['show_arrived' => true]))['counts']));

        $chart = $this->service()->chartEtaBuckets($this->filters());
        $values = $this->values();
        $data = array_column($chart['datasets'], 'data');

        $this->assertCount(5, $chart['labels']);
        $this->assertSame([[1, 1, 1, 0, 1], [1, 0, 0, 1, 1]], $data);
        $this->assertSame($values['arriving_week'], (string) $data[0][1]);
        $this->assertSame($values['arriving_two_weeks'], (string) $data[0][2]);
        $this->assertSame($values['delayed'], (string) ($data[0][0] + $data[1][0]));
    }

    public function test_filters_narrow_the_table(): void
    {
        $mix = $this->mix();
        $mix['a']->update(['doc_status_id' => $this->statusRow('Complete', Shipment::TYPE_DOC_STATUS)->id]);

        $this->assertSame([$mix['b']->id], $this->ids(['company_id' => $mix['b']->company_id]));
        $this->assertSame([$mix['a']->id], $this->ids(['doc_status_id' => $mix['a']->doc_status_id]));
        $this->assertSame([$mix['d']->id], $this->ids(['status_id' => [$mix['d']->shipment_status_id]]));
        $range = ['date_from' => $this->days(4), 'date_until' => $this->days(6)];
        $undated = collect($mix)->only(['a', 'd'])->pluck('id')->all();

        $this->assertSame([$mix['c']->id], $this->ids($range + ['include_undated' => false]));
        $this->assertEqualsCanonicalizing([$mix['c']->id, ...$undated], $this->ids($range));
    }

    public function test_the_product_and_category_filters_match_invoice_lines_or_the_order_items_like_the_products_column(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $own = $this->shipment(withItem: false);
        ShipmentItem::factory()->create(['shipment_id' => $own->id, 'product_id' => $product->id]);
        $other = $this->shipment();
        $filters = ['product_id' => $product->id];
        $byCategory = ['category_id' => $category->id];

        $this->assertSame([$own->id], $this->ids($filters));
        $this->assertSame([$own->id], $this->ids($byCategory));

        RegisteredOrderItem::factory()->create(['registered_order_id' => $this->order->id, 'product_id' => $product->id]);

        $this->assertEqualsCanonicalizing([$own->id, $other->id], $this->ids($filters));
        $this->assertEqualsCanonicalizing([$own->id, $other->id], $this->ids($byCategory));

        $check = collect(app(CheckRegistry::class)->all())->firstWhere('key', 'shipment.customs_quantity_above_shipped');
        $this->assertNotNull($check);
        $this->assertSame([], ($check->query)(Shipment::query(), $this->filters($filters))->get()->modelKeys());
    }

    public function test_missing_tracking_statuses_degrade_gracefully(): void
    {
        $mix = $this->mix();
        app()->instance(ReportStages::class, new class extends ReportStages
        {
            public function inTransit(): ?Status
            {
                return null;
            }

            public function arrived(): ?Status
            {
                return null;
            }
        });

        $values = $this->values();

        $this->assertSame('0', $values['arrived_unconfirmed']);
        $this->assertSame('3', $values['in_transit']);
        $this->assertContains($mix['d']->id, $this->ids());
        $this->assertContains($mix['i']->id, $this->ids());
    }

    public function test_export_rows_use_exactly_the_exported_labels(): void
    {
        $this->mix();

        $row = collect($this->service()->rows($this->filters()))->first();

        $this->assertSame(array_keys($this->service()->exportLabels()), array_keys($row));
    }

    public function test_the_registry_holds_the_eleven_checks_translated_in_all_locales(): void
    {
        $checks = collect(app(CheckRegistry::class)->forReport('in_transit'));

        $this->assertSame(11, $checks->count());
        $this->assertSame([2, 2, 7], [$checks->where('severity', 'error')->count(), $checks->where('severity', 'info')->count(), $checks->where('severity', 'warning')->count()]);

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $checks->each(function (Check $check): void {
                $this->assertNotSame($check->messageKey(), __($check->messageKey()));
                $this->assertNotSame($check->expectedKey(), __($check->expectedKey()));
            });
        }
    }

    private function history(Shipment $shipment, int $days): void
    {
        StatusHistory::where('statusable_type', Shipment::class)->where('statusable_id', $shipment->id)->delete();
        StatusHistory::create(['statusable_type' => Shipment::class, 'statusable_id' => $shipment->id, 'field' => 'shipment_status_id', 'to_status_id' => $shipment->shipment_status_id]);
        StatusHistory::where('statusable_type', Shipment::class)->where('statusable_id', $shipment->id)->update(['created_at' => now()->subDays($days)]);
    }

    private function stuck(array $attributes, int $days): Shipment
    {
        $shipment = $this->shipment($attributes);
        $this->history($shipment, $days);

        return $shipment;
    }

    private function pair(string $key): array
    {
        $inTransit = $this->statusRow('In Transit')->id;
        $arrived = $this->statusRow('Arrived At Destination')->id;
        $ok = ['exit_date' => $this->days(-5), 'etd' => $this->days(-10), 'eta' => $this->days(5)];

        return match ($key) {
            'shipment.in_transit_missing_eta' => [$this->shipment(['eta' => null] + $ok), $this->shipment($ok)],
            'shipment.in_transit_missing_etd' => [$this->shipment(['etd' => null] + $ok), $this->shipment($ok)],
            'shipment.eta_before_etd' => [$this->shipment(['etd' => $this->days(0), 'eta' => $this->days(-5)] + $ok), $this->shipment($ok)],
            'shipment.overdue_eta' => [$this->shipment(['eta' => $this->days(-3)] + $ok), $this->shipment($ok)],
            'shipment.arrived_without_warehouse_date' => [$this->shipment(['shipment_status_id' => $arrived] + $ok), $this->shipment(['shipment_status_id' => $arrived, 'warehouse_date' => $this->days(-1)] + $ok)],
            'shipment.departed_without_exit_date' => [$this->shipment(['shipment_status_id' => $inTransit, 'eta' => $this->days(5)]), $this->shipment(['shipment_status_id' => $inTransit] + $ok)],
            'shipment.no_invoice' => [$this->shipment($ok, false), $this->shipment($ok)],
            'shipment.stuck_in_stage' => [$this->stuck($ok + ['shipment_status_id' => $inTransit], 40), $this->stuck($ok + ['shipment_status_id' => $inTransit], 5)],
            'shipment.warehouse_before_exit_date' => [$this->shipment(['warehouse_date' => $this->days(-10)] + $ok), $this->shipment(['warehouse_date' => $this->days(-1)] + $ok)],
            'shipment.missing_carrier' => [$this->withoutCarrier($this->shipment($ok)), $this->shipment($ok)],
            'shipment.missing_bl_number' => [$this->shipment(['bl_number' => null] + $ok), $this->shipment($ok)],
        };
    }

    private function withoutCarrier(Shipment $shipment): Shipment
    {
        DB::table('companies')->where('id', $shipment->company_id)->update(['is_active' => 0]);

        return $shipment->refresh();
    }

    #[DataProvider('keys')]
    public function test_an_offender_is_returned_and_a_compliant_shipment_is_not(string $key): void
    {
        [$offender, $compliant] = $this->pair($key);
        $check = collect(InTransitChecks::all())->firstWhere('key', $key);

        $found = ($check->query)(Shipment::query(), $this->filters())->get();
        $record = $found->firstWhere('id', $offender->id);

        $this->assertContains($offender->id, $found->modelKeys());
        $this->assertNotContains($compliant->id, $found->modelKeys());
        $this->assertIsString(($check->offending)($record));
        $this->assertNotSame('', ($check->expected)($record));
    }

    public function test_stuck_in_stage_flags_a_missing_history_and_honours_the_threshold(): void
    {
        $shipment = $this->shipment(['shipment_status_id' => $this->statusRow('In Transit')->id]);
        StatusHistory::where('statusable_type', Shipment::class)->where('statusable_id', $shipment->id)->delete();
        DB::table('shipments')->where('id', $shipment->id)->update(['updated_at' => now()->subDays(45)]);
        $check = collect(InTransitChecks::all())->firstWhere('key', 'shipment.stuck_in_stage');

        $record = ($check->query)(Shipment::query(), $this->filters())->first();

        $this->assertStringContainsString(__('resources/report/strings.attention.no_history_note'), ($check->offending)($record));
        $this->assertSame(0, ($check->query)(Shipment::query(), $this->filters(['stuck_days' => 60]))->count());
    }

    public function test_the_stuck_threshold_changes_the_flagged_rows_and_the_expected_text(): void
    {
        $shipment = $this->stuck(['shipment_status_id' => $this->statusRow('In Transit')->id, 'exit_date' => $this->days(-5), 'eta' => $this->days(5)], 10);
        $check = collect(InTransitChecks::all())->firstWhere('key', 'shipment.stuck_in_stage');
        $tight = $this->filters(['stuck_days' => 5]);

        $this->assertSame([$shipment->id], ($check->query)(Shipment::query(), $tight)->get()->modelKeys());
        $this->assertSame([], ($check->query)(Shipment::query(), $this->filters())->get()->modelKeys());
        $this->assertStringContainsString('5', ($check->expected)($shipment, $tight));
        $this->assertStringContainsString('30', ($check->expected)($shipment, $this->filters()));
    }

    public function test_the_arrival_windows_move_card_lines_rows_buckets_and_labels_at_non_default_values(): void
    {
        $mix = $this->mix();
        $filters = $this->filters(['th_arrive_1' => 3, 'th_arrive_2' => 9]);
        $values = $this->values($filters);
        $lines = collect($this->service()->summary($filters))->flatMap(fn (array $card): array => $card['lines'])->pluck('label', 'quick');

        $this->assertSame(['0', '1'], [$values['arriving_week'], $values['arriving_two_weeks']]);
        $this->assertSame(__('resources/report/strings.in_transit.lines.within_week', ['days' => 3]), $lines['arriving_week']);
        $this->assertSame(__('resources/report/strings.in_transit.lines.two_weeks', ['from' => 4, 'to' => 9]), $lines['arriving_two_weeks']);
        $this->assertSame([], $this->service()->query($this->filters(['th_arrive_1' => 3, 'th_arrive_2' => 9, 'quick' => 'arriving_week']))->get()->modelKeys());
        $this->assertSame([$mix['c']->id], $this->service()->query($this->filters(['th_arrive_1' => 3, 'th_arrive_2' => 9, 'quick' => 'arriving_two_weeks']))->get()->modelKeys());

        $chart = $this->service()->chartEtaBuckets($filters);

        $this->assertSame([[1, 0, 1, 1, 1], [1, 0, 0, 1, 1]], array_column($chart['datasets'], 'data'));
        $this->assertSame(['0–3', '4–9'], [substr($chart['labels'][1], 0, strlen('0–3')), substr($chart['labels'][2], 0, strlen('4–9'))]);
        $this->assertStringContainsString('9', $chart['labels'][3]);
    }

    public function test_a_second_window_not_larger_than_the_first_is_treated_as_first_plus_one(): void
    {
        $this->assertSame([7, 14], InTransitChecks::arriveWindows(new ReportFilters));
        $this->assertSame([10, 11], InTransitChecks::arriveWindows($this->filters(['th_arrive_1' => 10, 'th_arrive_2' => 4])));
        $this->assertSame([7, 14], InTransitChecks::arriveWindows());
    }

    public function test_the_late_error_threshold_moves_the_line_the_rows_and_the_label(): void
    {
        $mix = $this->mix();

        foreach ([['th_late_error' => 2, 'rows' => ['f', 'g']], ['th_late_error' => 5, 'rows' => ['f']], ['th_late_error' => 30, 'rows' => []]] as $case) {
            $filters = $this->filters(['th_late_error' => $case['th_late_error']]);
            $rows = $this->service()->query($this->filters(['th_late_error' => $case['th_late_error'], 'quick' => 'delayed_14']))->get();

            $this->assertSame((string) count($case['rows']), $this->values($filters)['delayed_14']);
            $this->assertEqualsCanonicalizing(collect($mix)->only($case['rows'])->pluck('id')->all(), $rows->modelKeys());
        }

        $label = collect($this->service()->summary($this->filters(['th_late_error' => 5])))->flatMap(fn (array $card): array => $card['lines'])->firstWhere('quick', 'delayed_14')['label'];

        $this->assertSame(__('resources/report/strings.in_transit.lines.over_14', ['days' => 5]), $label);
        $this->assertStringContainsString('5', $label);
    }

    public function test_the_delayed_line_equals_the_overdue_check_rows(): void
    {
        $this->mix();
        $check = collect(InTransitChecks::all())->firstWhere('key', 'shipment.overdue_eta');

        $this->assertSame((int) $this->values()['delayed'], ($check->query)(Shipment::query(), $this->filters())->count());
    }

    public function test_cancelled_and_rejected_are_hidden_with_a_count_line_and_the_toggle_shows_them(): void
    {
        $mix = $this->mix();
        $rejected = $this->shipment(['status_id' => $this->statusRow('Rejected', Shipment::TYPE_SHIPMENT_STATUS)->id, 'warehouse_date' => $this->days(-1)]);
        $first = fn (ReportFilters $filters): array => collect($this->service()->summary($filters))->firstWhere('key', 'awaiting')['lines'];

        $hidden = collect($first($this->filters()))->firstWhere('label', __('resources/report/strings.in_transit.lines.void_hidden'));

        $this->assertSame('1', $hidden['value']);
        $this->assertArrayNotHasKey('quick', $hidden);
        $this->assertSame('2', collect($first($this->filters(['show_arrived' => true])))->firstWhere('label', $hidden['label'])['value']);
        $this->assertNotContains($mix['j']->id, $this->ids());
        $this->assertSame('4', $this->values()['in_transit']);

        $shown = $this->filters(['include_void' => true]);

        $this->assertNull(collect($first($shown))->firstWhere('label', $hidden['label']));
        $this->assertContains($mix['j']->id, $this->ids(['include_void' => true]));
        $this->assertSame('5', $this->values($shown)['in_transit']);
        $this->assertContains($rejected->id, $this->ids(['include_void' => true, 'show_arrived' => true]));
        $this->assertNotContains($rejected->id, $this->ids(['show_arrived' => true]));
        $this->assertSame(array_sum($this->service()->chartStages($shown)['counts']), count($this->ids(['include_void' => true])));
    }

    public function test_every_check_hides_voided_shipments_unless_the_toggle_is_on(): void
    {
        $void = $this->statusRow('Cancelled', Shipment::TYPE_SHIPMENT_STATUS)->id;
        $shipment = $this->shipment(['status_id' => $void, 'exit_date' => $this->days(-5), 'eta' => $this->days(-3), 'bl_number' => null]);
        $check = collect(InTransitChecks::all())->firstWhere('key', 'shipment.overdue_eta');
        $bl = collect(InTransitChecks::all())->firstWhere('key', 'shipment.missing_bl_number');

        $this->assertSame([], ($check->query)(Shipment::query(), $this->filters())->get()->modelKeys());
        $this->assertSame([$shipment->id], ($check->query)(Shipment::query(), $this->filters(['include_void' => true]))->get()->modelKeys());
        $this->assertSame([], ($bl->query)(Shipment::query(), $this->filters())->get()->modelKeys());
        $this->assertSame([$shipment->id], ($bl->query)(Shipment::query(), $this->filters(['include_void' => true]))->get()->modelKeys());
    }

    public function test_the_departure_basis_options_move_shipments_between_awaiting_and_in_transit(): void
    {
        $tracking = $this->statusRow('In Transit')->id;
        $exit = $this->shipment(['exit_date' => $this->days(-3)]);
        $etd = $this->shipment(['etd' => $this->days(-2)]);
        $status = $this->shipment(['shipment_status_id' => $tracking]);
        $both = $this->shipment(['exit_date' => $this->days(-4), 'shipment_status_id' => $tracking]);
        $none = $this->shipment();
        $expected = [
            'exit_or_tracking' => [[$exit, $status, $both], [$etd, $none]],
            'etd_too' => [[$exit, $etd, $status, $both], [$none]],
            'tracking_only' => [[$status, $both], [$exit, $etd, $none]],
        ];

        foreach ($expected as $basis => [$moving, $waiting]) {
            $extra = ['departure_basis' => $basis];
            $values = $this->values($this->filters($extra));

            $this->assertSame((string) count($moving), $values['in_transit'], $basis);
            $this->assertSame((string) count($waiting), $values['awaiting'], $basis);
            $this->assertEqualsCanonicalizing(collect($moving)->pluck('id')->all(), $this->ids($extra + ['quick' => 'in_transit']), $basis);
            $this->assertEqualsCanonicalizing(collect($waiting)->pluck('id')->all(), $this->ids($extra + ['quick' => 'awaiting']), $basis);
            $this->assertSame(count($moving), array_sum($this->service()->chartEtaBuckets($this->filters($extra))['datasets'][0]['data']), $basis);

            $states = $this->service()->query($this->filters($extra))->get()->pluck('state', 'id');

            $this->assertSame('in_transit', $states[$moving[0]->id], $basis);
            $this->assertSame('awaiting', $states[$waiting[0]->id], $basis);
        }

        $this->assertEqualsCanonicalizing($this->ids(), $this->ids(['departure_basis' => 'exit_or_tracking']));
        $this->assertEqualsCanonicalizing($this->ids(), $this->ids(['departure_basis' => 'garbage']));
        $this->assertSame('(shipments.exit_date is not null or '.InTransitChecks::trackingInTransitSql().')', InTransitChecks::departedSql());
        $this->assertNull($this->filters(['departure_basis' => 'garbage'])->departureBasis);
    }

    public function test_the_departure_basis_also_drives_the_missing_eta_check(): void
    {
        $etdOnly = $this->shipment(['etd' => $this->days(-2)]);
        $check = collect(InTransitChecks::all())->firstWhere('key', 'shipment.in_transit_missing_eta');

        $this->assertSame([], ($check->query)(Shipment::query(), $this->filters())->get()->modelKeys());
        $this->assertSame([$etdOnly->id], ($check->query)(Shipment::query(), $this->filters(['departure_basis' => 'etd_too']))->get()->modelKeys());
    }

    public function test_each_date_basis_moves_the_range_and_undated_rows_stay_unless_switched_off(): void
    {
        $arrivedStatus = $this->statusRow('Arrived At Destination')->id;
        $one = $this->shipment(['eta' => $this->days(5), 'etd' => $this->days(-10), 'exit_date' => $this->days(-5)]);
        $two = $this->shipment(['eta' => $this->days(30), 'etd' => $this->days(-2), 'exit_date' => $this->days(-1)]);
        $none = $this->shipment();
        $arrived = $this->shipment(['warehouse_date' => $this->days(-1), 'shipment_status_id' => $arrivedStatus]);
        $ids = fn (array $extra): array => $this->ids($extra + ['show_arrived' => true]);

        $this->assertEqualsCanonicalizing([$one->id, $none->id, $arrived->id], $ids(['date_from' => $this->days(1), 'date_until' => $this->days(10)]));
        $this->assertEqualsCanonicalizing([$two->id, $none->id, $arrived->id], $ids(['date_from' => $this->days(-6), 'date_basis' => 'etd']));
        $this->assertEqualsCanonicalizing([$two->id, $none->id, $arrived->id], $ids(['date_from' => $this->days(-3), 'date_basis' => 'exit_date']));
        $this->assertEqualsCanonicalizing([$arrived->id, $one->id, $two->id, $none->id], $ids(['date_from' => $this->days(-3), 'date_basis' => 'warehouse_date']));
        $this->assertSame([$arrived->id], $ids(['date_from' => $this->days(-3), 'date_basis' => 'warehouse_date', 'include_undated' => false]));
        $this->assertSame([$one->id], $ids(['date_from' => $this->days(1), 'date_until' => $this->days(10), 'include_undated' => false]));
        $this->assertEqualsCanonicalizing([$one->id, $none->id, $arrived->id], $ids(['date_from' => $this->days(1), 'date_until' => $this->days(10), 'date_basis' => 'bogus']));
    }

    public function test_the_shipment_status_filter_narrows_rows_and_cards(): void
    {
        $other = $this->statusRow('Pending Test', Shipment::TYPE_SHIPMENT_STATUS);
        $kept = $this->shipment(['status_id' => $other->id]);
        $plain = $this->shipment();

        $this->assertSame([$kept->id], $this->ids(['shipment_status_id' => [$other->id]]));
        $this->assertEqualsCanonicalizing([$kept->id, $plain->id], $this->ids());
        $this->assertSame('1', $this->values($this->filters(['shipment_status_id' => [$other->id]]))['awaiting']);
        $this->assertSame([], $this->ids(['shipment_status_id' => [$this->statusRow('Cancelled', Shipment::TYPE_SHIPMENT_STATUS)->id]]));
    }

    public function test_products_weight_state_seller_and_docs_progress_are_exported_and_sortable_sql_exists(): void
    {
        $seller = \App\Models\Company::factory()->seller()->create(['english_name' => 'Seller Transit X', 'name' => 'Seller Transit X']);
        $this->order->update(['seller_id' => $seller->id]);
        $shipment = $this->shipment(['exit_date' => $this->days(-3), 'docs' => [['name' => 'bl', 'received' => true], ['name' => 'inv', 'received' => false]]]);

        $row = collect($this->service()->rows($this->filters()))->first();
        $labels = $this->service()->exportLabels();

        $this->assertSame('Seller Transit X', $row['seller']);
        $this->assertSame('1/2', $row['docs_progress']);
        $this->assertSame('In transit', $row['state']);
        $this->assertSame(array_keys($labels), array_keys($row));
        $this->assertSame(__('resources/shipment/strings.table.docs_progress'), $labels['docs_progress']);
        $this->assertSame(__('resources/shipment/strings.table.registered_order'), $labels['registered_order']);
        $this->assertSame(__('resources/shipment/strings.form.warehouse_date'), $labels['warehouse_date']);
        $this->assertSame(__('resources/shipment/strings.table.bl_number'), $labels['bl_number']);
        $this->assertStringContainsString('registered_orders.id = shipments.registered_order_id', InTransitReportService::sellerNameSql());
        $this->assertStringContainsString('companies.id = shipments.company_id', InTransitReportService::carrierNameSql());
        $this->assertSame($shipment->id, $this->service()->searchState($this->service()->query($this->filters()), 'transit', $this->filters())->first()->id);
        $this->assertNull($this->service()->searchState($this->service()->query($this->filters()), 'zzz', $this->filters())->first());
    }

    public function test_the_new_checks_ignore_awaiting_shipments_and_blank_bl_numbers_count(): void
    {
        $awaiting = $this->withoutCarrier($this->shipment(['bl_number' => null]));
        $blank = $this->shipment(['bl_number' => '', 'exit_date' => $this->days(-3)]);
        $carrier = collect(InTransitChecks::all())->firstWhere('key', 'shipment.missing_carrier');
        $bl = collect(InTransitChecks::all())->firstWhere('key', 'shipment.missing_bl_number');

        $this->assertSame([], ($carrier->query)(Shipment::query(), $this->filters())->get()->modelKeys());
        $deleted = $this->shipment(['exit_date' => $this->days(-3)]);
        Company::whereKey($deleted->company_id)->first()->delete();

        $this->assertSame([$deleted->id], ($carrier->query)(Shipment::query(), $this->filters())->get()->modelKeys());
        $this->assertSame([$blank->id], ($bl->query)(Shipment::query(), $this->filters())->get()->modelKeys());
        $this->assertSame('info', $bl->severity);
        $this->assertSame('warning', $carrier->severity);
        $this->assertSame('error', collect(InTransitChecks::all())->firstWhere('key', 'shipment.warehouse_before_exit_date')->severity);
    }
}
