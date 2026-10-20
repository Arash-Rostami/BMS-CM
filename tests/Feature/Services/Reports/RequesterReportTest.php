<?php

namespace Tests\Feature\Services\Reports;

use App\Models\Custom;
use App\Models\Department;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Status;
use App\Models\User;
use App\Services\Reports\Builders\RequesterReportService;
use App\Services\Reports\Checks\Check;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Rules\RequesterStages;
use App\Services\Reports\Support\ReportFilters;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RequesterReportTest extends TestCase
{
    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        ReportStages::flush();
        app()->setLocale('en');
        $this->department = Department::factory()->create();
        $this->statusRow(Shipment::TYPE_TRACKING_STATUS, 'In Transit');
        $this->statusRow(Shipment::TYPE_TRACKING_STATUS, 'Arrived At Destination');
        $this->statusRow(Custom::TYPE_CLEARANCE_STATUS, 'Declared');
        $this->statusRow(Custom::TYPE_CLEARANCE_STATUS, 'Completed');
        ReportStages::flush();
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

    private function statusRow(string $type, string $name): Status
    {
        return Status::firstOrCreate(['english_type' => $type, 'english_name' => $name], ['type' => $type, 'name' => $name]);
    }

    private function filters(array $extra = []): ReportFilters
    {
        return ReportFilters::fromArray(['cost_center_id' => $this->department->id] + $extra);
    }

    private function service(): RequesterReportService
    {
        return app(RequesterReportService::class);
    }

    private function make(string $stage, array $request = [], array $shipment = [], bool $lines = true, ?string $unit = 'kg'): PurchaseRequestItem
    {
        $product = Product::factory()->create();
        $pr = PurchaseRequest::factory()->create($request + ['cost_center_id' => $this->department->id]);
        $item = PurchaseRequestItem::factory()->create(['purchase_request_id' => $pr->id, 'product_id' => $product->id, 'quantity' => 100, 'unit' => 'kg']);

        if ($stage === 'reviewing') {
            return $item;
        }

        $order = RegisteredOrder::factory()->create();
        $order->purchaseRequests()->attach($pr->id);

        if ($stage === 'sourcing') {
            return $item;
        }

        $dates = match ($stage) {
            'in_transit' => ['exit_date' => today()->subDays(3)->toDateString(), 'eta' => today()->addDays(3)->toDateString()],
            'arrived', 'at_customs', 'cleared' => ['warehouse_date' => today()->subDay()->toDateString()],
            default => [],
        };
        $created = Shipment::factory()->create($shipment + $dates + ['registered_order_id' => $order->id, 'shipment_status_id' => null, 'exit_date' => null, 'warehouse_date' => null, 'eta' => null]);

        if ($lines) {
            ShipmentItem::factory()->create(['shipment_id' => $created->id, 'product_id' => $product->id, 'quantity' => 40, 'unit' => $unit, 'net_weight' => 10, 'gross_weight' => 12]);
        }

        if (in_array($stage, ['at_customs', 'cleared'], true)) {
            Custom::factory()->create(['shipment_id' => $created->id, 'registered_order_id' => $order->id, 'clearance_status_id' => $this->statusRow(Custom::TYPE_CLEARANCE_STATUS, $stage === 'cleared' ? 'Completed' : 'Declared')->id]);
        }

        return $item;
    }

    private function stageOf(PurchaseRequestItem $item): string
    {
        return $this->service()->query($this->filters(['show_closed' => true]))->get()->firstWhere('id', $item->id)->stage;
    }

    public function test_every_stage_is_decided_by_the_most_advanced_record(): void
    {
        foreach (RequesterStages::STAGES as $stage) {
            $this->assertSame($stage, $this->stageOf($this->make($stage)), $stage);
        }
    }

    public function test_choosing_the_cleared_stage_lists_cleared_items_while_show_finished_is_off(): void
    {
        $cleared = $this->make('cleared');
        $open = $this->make('in_transit');
        $ids = fn (array $extra): array => $this->service()->query($this->filters($extra))->get()->modelKeys();

        $this->assertSame([$open->id], $ids([]));
        $this->assertSame([$cleared->id], $ids(['stage' => 'cleared']));
        $this->assertSame([$cleared->id], $ids(['stage' => 'cleared', 'show_closed' => false]));
        $this->assertNotContains(__('resources/report/strings.requester.lines.finished_hidden'), $this->lines(['stage' => 'cleared'])->pluck('label')->all());
        $this->assertContains(__('resources/report/strings.requester.lines.finished_hidden'), $this->lines()->pluck('label')->all());
    }

    public function test_every_clickable_line_equals_the_filtered_row_count_and_the_stage_lines_sum_to_the_total(): void
    {
        foreach (RequesterStages::STAGES as $stage) {
            $this->make($stage);
        }
        $this->make('in_transit', ['required_by_date' => today()->subDays(2)->toDateString()]);
        $this->make('in_transit', [], ['eta' => null]);
        $this->make('awaiting', [], [], false);
        $this->make('arrived', [], [], true, 'pcs');

        $cards = $this->service()->summary($this->filters());
        $this->assertCount(4, $cards);
        $stageSum = 0;
        $total = 0;

        foreach ($cards as $card) {
            foreach ($card['lines'] as $line) {
                if (! isset($line['quick'])) {
                    continue;
                }

                $this->assertSame((int) $line['value'], $this->service()->query($this->filters(['quick' => $line['quick']]))->count(), $line['quick']);
                $line['quick'] === 'all' ? $total = (int) $line['value'] : (str_starts_with($line['quick'], 'stage_') ? $stageSum += (int) $line['value'] : null);
            }
        }

        $this->assertSame($total, $stageSum);
        $this->assertSame(11, $total);
        $this->assertSame($total - 1, $this->service()->query($this->filters(['quick' => 'bogus']))->count());
        $this->assertSame($total, $this->service()->query($this->filters(['quick' => 'bogus', 'show_closed' => true]))->count());
        $this->assertCount(2, collect($cards[3]['lines'])->filter(fn (array $line): bool => ! isset($line['quick'])));
        $this->assertContains('weight_unknown_unshipped', collect($cards[3]['lines'])->pluck('quick')->all());
    }

    public function test_the_charts_reconcile_with_the_cards(): void
    {
        foreach (RequesterStages::STAGES as $stage) {
            $this->make($stage);
        }
        $this->make('awaiting', ['required_by_date' => today()->subDays(2)->toDateString()]);
        $this->make('cleared', ['required_by_date' => today()->subDays(2)->toDateString()]);

        $stages = $this->service()->chartStages($this->filters());
        $late = $this->service()->chartLateByStage($this->filters());
        $cardLate = (int) collect($this->service()->summary($this->filters())[3]['lines'])->firstWhere('quick', 'late')['value'];

        $this->assertSame(9, array_sum($stages['counts']));
        $this->assertSame($stages['counts'], array_map(fn (int $a, int $b): int => $a + $b, $late['on_time'], $late['late']));
        $this->assertSame($cardLate, array_sum($late['late']));
        $this->assertSame(1, $cardLate);
    }

    public function test_rows_carry_quantities_and_weights_but_no_money_columns(): void
    {
        $this->make('in_transit');
        $this->make('arrived', [], [], true, 'pcs');

        $rows = collect($this->service()->rows($this->filters()))->values();
        $transit = $rows->firstWhere('stage', RequesterStages::label('in_transit'));
        $mismatch = $rows->firstWhere('stage', RequesterStages::label('arrived'));
        $keys = [...array_keys($rows->first()), ...array_keys($this->service()->exportLabels())];

        $this->assertEquals([40.0, 60.0, 12.0, 10.0], [$transit['shipped_quantity'], $transit['remaining_quantity'], $transit['gross_weight'], $transit['net_weight']]);
        $this->assertNull($mismatch['shipped_quantity']);
        $this->assertNull($mismatch['remaining_quantity']);

        foreach ($keys as $key) {
            $this->assertDoesNotMatchRegularExpression('/price|amount|cost|supplier|seller|buyer|bank|payment|currency/i', $key);
        }

        $this->assertArrayNotHasKey('estimated_cost', $this->service()->query($this->filters())->first()->getAttributes());
    }

    public function test_a_request_without_cost_center_is_still_listed_and_flagged(): void
    {
        $item = $this->make('reviewing', ['cost_center_id' => null]);
        $filters = ReportFilters::fromArray(['product_id' => $item->product_id]);
        $check = app(CheckRegistry::class)->all()->firstWhere('key', 'purchase_request.missing_cost_center');

        $this->assertSame([$item->id], $this->service()->query($filters)->get()->pluck('id')->all());
        $this->assertSame([$item->purchase_request_id], ($check->query)(PurchaseRequest::query(), $filters)->pluck('purchase_requests.id')->all());
    }

    public function test_the_requester_checks_flag_late_requests_and_blank_invoice_lines(): void
    {
        $late = $this->make('awaiting', ['required_by_date' => today()->subDays(3)->toDateString()]);
        $blank = $this->make('awaiting');
        $order = $blank->purchaseRequest->registeredOrders()->first();
        ShipmentItem::factory()->create(['shipment_id' => Shipment::where('registered_order_id', $order->id)->value('id'), 'product_id' => null]);
        $registry = app(CheckRegistry::class);
        $ids = fn (string $key, string $table, ReportFilters $filters): array => ($registry->all()->firstWhere('key', $key)->query)(($registry->all()->firstWhere('key', $key)->model)::query(), $filters)->pluck("{$table}.id")->all();

        $this->assertContains($late->purchase_request_id, $ids('purchase_request.required_by_passed', 'purchase_requests', $this->filters()));
        $this->assertNotContains($blank->purchase_request_id, $ids('purchase_request.required_by_passed', 'purchase_requests', $this->filters()));
        $this->assertCount(1, $ids('shipment.item_without_product', 'shipments', new ReportFilters(registeredOrderId: $order->id)));
        $this->assertCount(9, $registry->forReport('requester'));
    }

    public function test_the_stage_counts_are_cached_and_refreshed_when_a_request_is_saved(): void
    {
        $this->make('reviewing');
        $total = fn (): int => (int) collect($this->service()->summary($this->filters()))->first()['lines'][0]['value'];

        $this->assertSame(1, $total());
        $this->assertSame(1, array_sum($this->service()->chartStages($this->filters())['counts']));

        $this->make('reviewing');

        $this->assertSame(2, $total());
        $this->assertSame(2, array_sum($this->service()->chartStages($this->filters())['counts']));
        $this->assertSame(2, array_sum($this->service()->chartLateByStage($this->filters())['on_time']) + array_sum($this->service()->chartLateByStage($this->filters())['late']));
    }

    public function test_dates_and_check_strings_follow_the_calendar(): void
    {
        $item = $this->make('awaiting', ['required_by_date' => '2020-03-20']);
        $number = PurchaseRequest::find($item->purchase_request_id)->pr_number;
        $check = app(CheckRegistry::class)->all()->firstWhere('key', 'purchase_request.required_by_passed');
        $registry = app(CheckRegistry::class);
        $column = fn (): string => collect($this->service()->rows($this->filters()))->firstWhere('pr_number', $number)['required_by_date'];
        $offending = fn (): string => $registry->evaluate($check, $this->filters())->firstWhere('id', $item->purchase_request_id)['offending'];

        session(['calendar_type' => 'gregorian']);
        $this->assertSame(toGregorianDate('2020-03-20'), $column());
        $gregorian = $offending();

        session(['calendar_type' => 'jalali']);
        $this->assertSame(toPersianDate('2020-03-20'), $column());
        $jalali = $offending();
        session()->forget('calendar_type');

        $this->assertStringContainsString(toGregorianDate('2020-03-20'), $gregorian);
        $this->assertStringContainsString(toPersianDate('2020-03-20'), $jalali);
        $this->assertNotSame($gregorian, $jalali);
    }

    private function lines(array $extra = []): Collection
    {
        return collect($this->service()->summary($this->filters($extra)))->flatMap(fn (array $card): array => $card['lines']);
    }

    private function assertLinesEqualRows(array $extra = []): void
    {
        foreach ($this->lines($extra)->filter(fn (array $line): bool => isset($line['quick'])) as $line) {
            $this->assertSame((int) $line['value'], $this->service()->query($this->filters($extra + ['quick' => $line['quick']]))->count(), $line['quick'].' '.json_encode($extra));
        }
    }

    private function check(string $key): Check
    {
        return app(CheckRegistry::class)->all()->firstWhere('key', $key);
    }

    private function flagged(string $key, array $extra = [], ?ReportFilters $filters = null): array
    {
        $check = $this->check($key);

        return ($check->query)(($check->model)::query(), $filters ?? $this->filters($extra))->pluck((new ($check->model))->getTable().'.id')->all();
    }

    private function shipmentOf(PurchaseRequestItem $item): Shipment
    {
        return Shipment::where('registered_order_id', $item->purchaseRequest->registeredOrders()->first()->id)->firstOrFail();
    }

    private function setShipped(PurchaseRequestItem $item, float $quantity, string $unit = 'kg'): void
    {
        ShipmentItem::where('shipment_id', $this->shipmentOf($item)->id)->update(['quantity' => $quantity, 'unit' => $unit]);
    }

    public function test_finished_items_are_hidden_by_default_and_shown_with_the_toggle_while_cards_count_everything(): void
    {
        $open = $this->make('in_transit');
        $done = $this->make('cleared');
        $second = $this->make('cleared', [], [], false);

        $default = $this->service()->query($this->filters())->get()->pluck('id')->all();
        $shown = $this->service()->query($this->filters(['show_closed' => true]))->get()->pluck('id')->all();
        $hidden = fn (array $extra): ?string => $this->lines($extra)->firstWhere('label', __('resources/report/strings.requester.lines.finished_hidden'))['value'] ?? null;

        $this->assertSame([$open->id], $default);
        $this->assertEqualsCanonicalizing([$open->id, $done->id, $second->id], $shown);
        $this->assertSame('2', $hidden([]));
        $this->assertNull($hidden(['show_closed' => true]));
        $this->assertSame(2, $this->service()->chartStages($this->filters())['counts'][6]);
        $this->assertSame('3', $this->lines()->firstWhere('quick', 'all')['value']);
        $this->assertEqualsCanonicalizing([$done->id, $second->id], $this->service()->query($this->filters(['quick' => 'stage_cleared']))->get()->pluck('id')->all());
        $this->assertCount(3, $this->service()->query($this->filters(['quick' => 'all']))->get());
        $this->assertSame([$second->id], $this->service()->query($this->filters(['quick' => 'weight_unknown']))->get()->pluck('id')->all());
        $this->assertSame('1', $this->lines()->firstWhere('quick', 'weight_unknown')['value']);
    }

    public function test_the_widened_quick_lines_equal_their_rows_with_the_toggle_off_and_on(): void
    {
        foreach (RequesterStages::STAGES as $stage) {
            $this->make($stage);
        }
        $this->make('cleared', [], [], false);
        $this->make('awaiting', ['required_by_date' => today()->subDays(4)->toDateString()], [], false);

        foreach ([[], ['show_closed' => true], ['th_arrive_1' => 2, 'th_late_grace' => 1], ['include_void' => true, 'departure_basis' => 'etd_too']] as $extra) {
            $this->assertLinesEqualRows($extra);
        }

        $this->assertSame('2', $this->lines()->firstWhere('quick', 'weight_unknown')['value']);
        $this->assertSame(['cleared'], $this->service()->query($this->filters(['quick' => 'weight_unknown']))->get()->pluck('stage')->intersect(['cleared'])->unique()->values()->all());
    }

    public function test_the_cleared_stage_follows_the_chosen_clearance_stage(): void
    {
        $item = $this->make('at_customs');
        $declared = $this->statusRow(Custom::TYPE_CLEARANCE_STATUS, 'Declared');

        $this->assertSame('at_customs', $this->stageOf($item));
        $this->assertSame('cleared', $this->service()->query($this->filters(['cleared_from_stage' => $declared->id, 'show_closed' => true]))->get()->firstWhere('id', $item->id)->stage);
        $this->assertSame([], $this->service()->query($this->filters(['cleared_from_stage' => $declared->id]))->get()->pluck('id')->all());
        $this->assertSame('1', $this->lines(['cleared_from_stage' => $declared->id])->firstWhere('quick', 'stage_cleared')['value']);
    }

    public function test_cancelled_shipments_are_ignored_unless_included(): void
    {
        $item = $this->make('awaiting');
        $cancelled = $this->statusRow(Shipment::TYPE_SHIPMENT_STATUS, 'Cancelled');
        ReportStages::flush();
        Shipment::whereKey($this->shipmentOf($item)->id)->update(['status_id' => $cancelled->id]);

        $this->assertSame('sourcing', $this->stageOf($item));
        $this->assertSame('awaiting', $this->service()->query($this->filters(['include_void' => true]))->get()->firstWhere('id', $item->id)->stage);
        $this->assertSame([], $this->service()->query($this->filters())->get()->firstWhere('id', $item->id)->shipment_labels);
        $this->assertCount(1, $this->service()->query($this->filters(['include_void' => true]))->get()->firstWhere('id', $item->id)->shipment_labels);
    }

    public function test_the_arriving_window_follows_the_first_arrival_threshold(): void
    {
        $this->make('in_transit', [], ['eta' => today()->addDays(10)->toDateString()]);
        $line = fn (array $extra): array => $this->lines($extra)->firstWhere('quick', 'arriving');

        $this->assertSame('0', $line([])['value']);
        $this->assertSame(__('resources/report/strings.requester.lines.arriving', ['days' => 7]), $line([])['label']);
        $this->assertSame('1', $line(['th_arrive_1' => 10])['value']);
        $this->assertSame(__('resources/report/strings.requester.lines.arriving', ['days' => 10]), $line(['th_arrive_1' => 10])['label']);
        $this->assertSame('0', $line(['th_arrive_1' => 9])['value']);
        $this->assertCount(1, $this->service()->query($this->filters(['quick' => 'arriving', 'th_arrive_1' => 10]))->get());
    }

    public function test_the_late_rule_follows_the_grace_threshold_in_the_cards_charts_and_check(): void
    {
        $item = $this->make('awaiting', ['required_by_date' => today()->subDays(3)->toDateString()]);
        $late = fn (array $extra): string => $this->lines($extra)->firstWhere('quick', 'late')['value'];

        $this->assertSame('1', $late([]));
        $this->assertSame('1', $late(['th_late_grace' => 2]));
        $this->assertSame('0', $late(['th_late_grace' => 3]));
        $this->assertSame('0', $late(['th_late_grace' => 5]));
        $this->assertSame(__('resources/report/strings.requester.lines.late_grace', ['days' => 2]), $this->lines(['th_late_grace' => 2])->firstWhere('quick', 'late')['label']);
        $this->assertSame(__('resources/report/strings.requester.lines.late'), $this->lines()->firstWhere('quick', 'late')['label']);
        $this->assertSame(0, array_sum($this->service()->chartLateByStage($this->filters(['th_late_grace' => 5]))['late']));
        $this->assertSame(1, array_sum($this->service()->chartLateByStage($this->filters(['th_late_grace' => 1]))['late']));
        $this->assertContains($item->purchase_request_id, $this->flagged('purchase_request.required_by_passed'));
        $this->assertContains($item->purchase_request_id, $this->flagged('purchase_request.required_by_passed', ['th_late_grace' => 2]));
        $this->assertNotContains($item->purchase_request_id, $this->flagged('purchase_request.required_by_passed', ['th_late_grace' => 3]));
    }

    public function test_the_departure_basis_moves_items_between_awaiting_and_in_transit(): void
    {
        $item = $this->make('awaiting', [], ['etd' => today()->addDays(2)->toDateString()]);

        $this->assertSame('awaiting', $this->stageOf($item));
        $this->assertSame('in_transit', $this->service()->query($this->filters(['departure_basis' => 'etd_too']))->get()->firstWhere('id', $item->id)->stage);
        $this->assertSame('awaiting', $this->service()->query($this->filters(['departure_basis' => 'tracking_only']))->get()->firstWhere('id', $item->id)->stage);
    }

    public function test_the_requester_and_status_filters_narrow_rows_charts_and_checks(): void
    {
        $other = User::factory()->create();
        $authorized = $this->statusRow(PurchaseRequest::TYPE_PURCHASE_REQUEST, 'Authorized');
        $declined = $this->statusRow(PurchaseRequest::TYPE_PURCHASE_REQUEST, 'Declined');
        $mine = $this->make('reviewing', ['required_by_date' => null, 'status_id' => $authorized->id]);
        $theirs = $this->make('reviewing', ['required_by_date' => null, 'status_id' => $declined->id, 'requester_id' => $other->id]);
        $ids = fn (array $extra): array => $this->service()->query($this->filters($extra))->get()->pluck('id')->all();
        $flagged = fn (array $extra): array => $this->flagged('purchase_request.missing_required_by_date', $extra);

        $this->assertEqualsCanonicalizing([$mine->id, $theirs->id], $ids([]));
        $this->assertSame([$theirs->id], $ids(['requester_id' => $other->id]));
        $this->assertSame([$mine->id], $ids(['requester_id' => $mine->purchaseRequest->requester_id]));
        $this->assertSame([$mine->id], $ids(['status_id' => [$authorized->id]]));
        $this->assertSame([$theirs->id], $ids(['status_id' => [$declined->id]]));
        $this->assertEqualsCanonicalizing([$mine->id, $theirs->id], $ids(['status_id' => [$authorized->id, $declined->id]]));
        $this->assertEqualsCanonicalizing([$mine->purchase_request_id, $theirs->purchase_request_id], $flagged([]));
        $this->assertSame([$theirs->purchase_request_id], $flagged(['requester_id' => $other->id]));
        $this->assertSame([$mine->purchase_request_id], $flagged(['status_id' => [$authorized->id]]));
        $this->assertSame([1, 0], [array_sum($this->service()->chartStages($this->filters(['requester_id' => $other->id]))['counts']), array_sum($this->service()->chartStages($this->filters(['requester_id' => $other->id, 'status_id' => [$authorized->id]]))['counts'])]);
    }

    public function test_the_date_basis_and_undated_toggle_apply_to_rows_and_checks(): void
    {
        $dated = $this->make('reviewing', ['required_by_date' => today()->addDays(5)->toDateString(), 'created_at' => today()->subDays(60)]);
        $undated = $this->make('reviewing', ['required_by_date' => null]);
        $range = ['date_from' => today()->addDays(1)->toDateString(), 'date_until' => today()->addDays(10)->toDateString()];
        $ids = fn (array $extra): array => $this->service()->query($this->filters($range + $extra))->get()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$dated->id, $undated->id], $ids([]));
        $created = ['date_basis' => 'created_at', 'date_from' => today()->subDays(70)->toDateString(), 'date_until' => today()->subDays(50)->toDateString()];
        $flagged = fn (array $extra): array => $this->flagged('purchase_request.missing_required_by_date', $range + $extra);

        $this->assertSame([$dated->id], $ids(['include_undated' => false]));
        $this->assertSame([$dated->id], $this->service()->query($this->filters($created))->get()->pluck('id')->all());
        $this->assertSame([], $this->service()->query($this->filters([...$created, 'date_from' => today()->subDays(55)->toDateString()]))->get()->pluck('id')->all());
        $this->assertSame([$undated->purchase_request_id], $flagged([]));
        $this->assertSame([], $flagged(['include_undated' => false]));
        $this->assertSame([], $this->flagged('purchase_request.missing_required_by_date', $created));
    }

    public function test_the_reviewing_stage_carries_the_purchase_request_status_name(): void
    {
        $row = $this->statusRow(PurchaseRequest::TYPE_PURCHASE_REQUEST, 'Under Review');
        ReportStages::flush();

        $this->assertSame($row->localized_name, RequesterStages::label('reviewing'));
        $this->assertSame('Under Review', RequesterStages::label('reviewing'));

        Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)->where('english_name', 'Under Review')->delete();
        ReportStages::flush();

        foreach (['en' => 'Under Review', 'fa' => 'در حال بررسی', 'fr' => 'En cours de révision'] as $locale => $name) {
            app()->setLocale($locale);
            $this->assertSame($name, RequesterStages::label('reviewing'), $locale);
        }
    }

    public function test_the_missing_required_by_date_check_flags_only_undated_requests(): void
    {
        $undated = $this->make('reviewing', ['required_by_date' => null]);
        $dated = $this->make('reviewing');

        $this->assertContains($undated->purchase_request_id, $this->flagged('purchase_request.missing_required_by_date'));
        $this->assertNotContains($dated->purchase_request_id, $this->flagged('purchase_request.missing_required_by_date'));
        $this->assertSame('info', $this->check('purchase_request.missing_required_by_date')->severity);
    }

    public function test_the_shipped_above_requested_check_flags_only_comparable_overshipment(): void
    {
        $over = $this->make('awaiting');
        $exact = $this->make('awaiting');
        $units = $this->make('awaiting');
        $shared = $this->make('awaiting');
        $this->setShipped($over, 150);
        $this->setShipped($exact, 100);
        $this->setShipped($units, 150, 'pcs');
        $this->setShipped($shared, 150);
        $twin = PurchaseRequest::factory()->create(['cost_center_id' => $this->department->id]);
        PurchaseRequestItem::factory()->create(['purchase_request_id' => $twin->id, 'product_id' => $shared->product_id, 'quantity' => 10, 'unit' => 'kg']);
        $twin->registeredOrders()->attach($shared->purchaseRequest->registeredOrders()->first()->id);

        $flagged = $this->flagged('purchase_request.shipped_above_requested');

        $this->assertContains($over->purchase_request_id, $flagged);
        $this->assertNotContains($exact->purchase_request_id, $flagged);
        $this->assertNotContains($units->purchase_request_id, $flagged);
        $this->assertNotContains($shared->purchase_request_id, $flagged);
        $this->assertNotContains($twin->id, $flagged);
        $this->assertSame('warning', $this->check('purchase_request.shipped_above_requested')->severity);
        $this->assertSame(1, collect($flagged)->intersect([$over->purchase_request_id, $exact->purchase_request_id, $units->purchase_request_id, $shared->purchase_request_id])->count());
    }

    public function test_the_no_order_check_uses_the_supply_threshold_and_skips_declined_and_ordered_requests(): void
    {
        $stale = $this->make('reviewing', ['created_at' => today()->subDays(40)]);
        $fresh = $this->make('reviewing', ['created_at' => today()->subDays(5)]);
        $ordered = $this->make('sourcing', ['created_at' => today()->subDays(40)]);
        $declined = $this->make('reviewing', ['created_at' => today()->subDays(40), 'status_id' => $this->statusRow(PurchaseRequest::TYPE_PURCHASE_REQUEST, 'Declined')->id]);
        ReportStages::flush();

        $ids = fn (array $extra): array => $this->flagged('purchase_request.no_order_after_n_days', $extra);
        $check = $this->check('purchase_request.no_order_after_n_days');

        $this->assertContains($stale->purchase_request_id, $ids([]));
        $this->assertNotContains($fresh->purchase_request_id, $ids([]));
        $this->assertNotContains($ordered->purchase_request_id, $ids([]));
        $this->assertNotContains($declined->purchase_request_id, $ids([]));
        $this->assertContains($fresh->purchase_request_id, $ids(['th_supply' => 4]));
        $this->assertNotContains($stale->purchase_request_id, $ids(['th_supply' => 41]));
        $this->assertContains($stale->purchase_request_id, $ids(['th_supply' => 39]));
        $this->assertStringContainsString('45', ($check->expected)(null, $this->filters(['th_supply' => 45])));
        $this->assertStringContainsString('30', ($check->expected)(null, $this->filters()));
        $this->assertSame(__('resources/report/strings.attention.days', ['count' => 40]), ($check->offending)(PurchaseRequest::find($stale->purchase_request_id)));
    }

    public function test_every_new_check_has_a_message_and_expectation_in_every_locale(): void
    {
        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);

            foreach (['missing_required_by_date', 'shipped_above_requested', 'no_order_after_n_days'] as $key) {
                $check = $this->check("purchase_request.{$key}");

                $this->assertNotSame($check->messageKey(), __($check->messageKey()), "{$locale}.{$key}");
                $this->assertNotSame($check->expectedKey(), __($check->expectedKey()), "{$locale}.{$key}");
            }
        }
    }
}
