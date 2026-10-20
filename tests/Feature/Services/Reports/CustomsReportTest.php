<?php

namespace Tests\Feature\Services\Reports;

use App\Filament\Resources\CustomResource;
use App\Models\Category;
use App\Models\Company;
use App\Models\Custom;
use App\Models\Product;
use App\Models\RegisteredOrder;
use App\Models\RegisteredOrderItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Status;
use App\Models\StatusHistory;
use App\Services\Reports\Builders\CustomsReportService;
use App\Services\Reports\Checks\Check;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Checks\CustomChecks;
use App\Services\Reports\Checks\ShipmentChecks;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\ReportFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CustomsReportTest extends TestCase
{
    private array $statuses = [];

    private RegisteredOrder $builderOrder;

    private RegisteredOrder $checksOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        ReportStages::flush();
        app()->setLocale('en');
        $this->builderOrder = RegisteredOrder::factory()->create();
        $this->checksOrder = RegisteredOrder::factory()->create();
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
        $checks = array_filter(
            [...CustomChecks::all(), ...ShipmentChecks::all()],
            fn (Check $check): bool => in_array('customs', $check->reports, true)
        );

        return array_map(fn (Check $check): array => [$check->key], array_values($checks));
    }

    private function builderService(): CustomsReportService
    {
        return app(CustomsReportService::class);
    }

    private function builderFilters(array $extra = []): ReportFilters
    {
        return ReportFilters::fromArray(['registered_order_id' => $this->builderOrder->id] + $extra);
    }

    private function statusRow(string $name, string $type = Custom::TYPE_CLEARANCE_STATUS): Status
    {
        return $this->statuses["{$type}:{$name}"] ??= Status::firstOrCreate(
            ['english_type' => $type, 'english_name' => $name],
            ['type' => $type, 'name' => $name],
        );
    }

    private function shipment(array $extra = [], bool $withItem = true, ?RegisteredOrder $order = null): Shipment
    {
        $shipment = Shipment::factory()->create($extra + [
            'registered_order_id' => ($order ?? $this->checksOrder)->id,
            'warehouse_date' => today()->subDays(12)->toDateString(),
            'customs_quantity' => 10,
            'shipped_quantity' => 50,
        ]);

        if ($withItem) {
            $this->item($shipment);
        }

        return $shipment;
    }

    private function item(Shipment $shipment, ?Product $product = null, array $extra = []): ShipmentItem
    {
        return ShipmentItem::factory()->create($extra + [
            'shipment_id' => $shipment->id,
            'product_id' => ($product ?? Product::factory()->create())->id,
            'gross_weight' => 100,
        ]);
    }

    private function builderFile(?string $clearance = 'Declared', array $extra = [], ?Shipment $shipment = null): Custom
    {
        $order = $this->builderOrder;

        return Custom::factory()->create($extra + [
            'registered_order_id' => $order->id,
            'shipment_id' => ($shipment ?? $this->shipment(order: $order))->id,
            'clearance_status_id' => $clearance ? $this->statusRow($clearance)->id : null,
            'bank_guarantee_status_id' => $this->statusRow('Returned', Custom::TYPE_BANK_GUARANTEE_STATUS)->id,
            'commitment_status_id' => $this->statusRow('Completed', Custom::TYPE_COMMITMENT_STATUS)->id,
            'clearance_date' => null,
            'doc_submission_date' => today()->subDays(10)->toDateString(),
            'ten_percent_exit_date' => today()->subDays(2)->toDateString(),
            'clearance_type' => 'definitive',
        ]);
    }

    private function customsFile(array $attributes = [], ?string $clearance = 'Declared', ?RegisteredOrder $order = null, ?Shipment $shipment = null): Custom
    {
        $order ??= $this->checksOrder;

        return Custom::factory()->create($attributes + [
            'registered_order_id' => $order->id,
            'shipment_id' => ($shipment ?? $this->shipment(order: $order))->id,
            'clearance_status_id' => $clearance ? $this->statusRow($clearance)->id : null,
            'bank_guarantee_status_id' => $this->statusRow('Returned', Custom::TYPE_BANK_GUARANTEE_STATUS)->id,
            'commitment_status_id' => $this->statusRow('Completed', Custom::TYPE_COMMITMENT_STATUS)->id,
            'clearance_date' => null,
            'doc_submission_date' => today()->subDays(10)->toDateString(),
            'ten_percent_exit_date' => today()->subDays(2)->toDateString(),
            'clearance_type' => 'definitive',
            'declaration_no' => 'DEC-'.uniqid(),
        ]);
    }

    private function offending(string $key, ?RegisteredOrder $order = null): Model
    {
        return match ($key) {
            'custom.cleared_before_arrival' => $this->customsFile(['doc_submission_date' => '2026-01-10', 'clearance_date' => '2026-01-05'], order: $order),
            'custom.cleared_without_clearance_date' => $this->customsFile([], 'Completed', $order),
            'custom.missing_doc_submission_date' => $this->customsFile(['doc_submission_date' => null], order: $order),
            'custom.open_over_threshold' => $this->customsFile(['doc_submission_date' => today()->subDays(40)->toDateString()], order: $order),
            'custom.ten_percent_open_without_exit_date' => $this->customsFile(['ten_percent_exit_date' => null], '10% Remaining', $order),
            'custom.guarantee_not_returned_after_completion' => $this->customsFile(['bank_guarantee_status_id' => $this->statusRow('Not Returned', Custom::TYPE_BANK_GUARANTEE_STATUS)->id], 'Completed', $order),
            'custom.clearance_type_missing' => $this->customsFile(['clearance_type' => null], order: $order),
            'custom.shipment_not_arrived' => $this->customsFile(shipment: $this->shipment(['warehouse_date' => null], order: $order), order: $order),
            'custom.without_invoice' => $this->customsFile(shipment: $this->shipment(withItem: false, order: $order), order: $order),
            'custom.stuck_in_stage' => $this->stuckInStage($this->customsFile(order: $order), 40),
            'custom.missing_declaration_no' => $this->customsFile(['declaration_no' => null], order: $order),
            'custom.doc_submission_after_clearance' => $this->customsFile(['doc_submission_date' => '2026-02-10', 'clearance_date' => '2026-02-05'], order: $order),
            'shipment.customs_quantity_above_shipped' => $this->quantityOverShipped($order),
            default => throw new RuntimeException("Unknown check [{$key}]"),
        };
    }

    private function compliant(string $key): Model
    {
        return $key === 'shipment.customs_quantity_above_shipped'
            ? $this->shipment(order: $this->checksOrder)
            : $this->customsFile();
    }

    private function stuckInStage(Custom $file, int $days): Custom
    {
        $this->history($file, $days);

        return $file;
    }

    private function history(Custom $file, int $days): void
    {
        StatusHistory::create(['statusable_type' => Custom::class, 'statusable_id' => $file->id, 'field' => 'clearance_status_id', 'to_status_id' => $file->clearance_status_id]);
        StatusHistory::where('statusable_type', Custom::class)
            ->where('statusable_id', $file->id)
            ->where('field', 'clearance_status_id')
            ->update(['created_at' => now()->subDays($days)]);
    }

    private function quantityOverShipped(?RegisteredOrder $order): Shipment
    {
        $shipment = $this->shipment(['customs_quantity' => 100, 'shipped_quantity' => 50], order: $order);
        $this->customsFile([], 'Declared', $order, $shipment);

        return $shipment;
    }

    private function card(string $key): array
    {
        return collect($this->builderService()->summary($this->builderFilters()))->firstWhere('key', $key);
    }

    private function check(string $key): Check
    {
        return collect([...CustomChecks::all(), ...ShipmentChecks::all()])->firstWhere('key', $key);
    }

    private function ids(string $key, ?ReportFilters $filters = null): array
    {
        $check = $this->check($key);
        $filters ??= new ReportFilters(registeredOrderId: $this->checksOrder->id);

        return ($check->query)(($check->model)::query(), $filters)->get()->modelKeys();
    }

    public function test_the_open_card_counts_open_files_and_their_missing_statuses(): void
    {
        $this->builderFile('Declared');
        $this->builderFile(null);
        $this->builderFile('Completed');

        $values = collect($this->card('open')['lines'])->pluck('value', 'label');

        $this->assertSame('2', $values[__('resources/report/strings.customs.lines.count')]);
        $this->assertSame('1', $values[__('resources/report/strings.customs.lines.no_status')]);
    }

    public function test_the_stage_card_sums_the_open_files_per_ordered_stage(): void
    {
        $this->builderFile('Declared');
        $this->builderFile('Appraisal');
        $this->builderFile('Completed');

        $lines = collect($this->card('per_stage')['lines']);

        $this->assertSame(2, $lines->sum(fn (array $line): int => (int) $line['value']));
        $this->assertSame('1', (string) $lines->firstWhere('label', 'Declared')['value']);
    }

    public function test_files_pending_editing_are_counted_in_the_stage_card_so_the_numbers_reconcile(): void
    {
        $this->builderFile('Declared');
        $this->builderFile('Pending Editing');

        $open = (int) collect($this->card('open')['lines'])->first()['value'];
        $stages = collect($this->card('per_stage')['lines'])->sum(fn (array $line): int => (int) $line['value']);

        $this->assertSame(2, $open);
        $this->assertSame($open, $stages);
    }

    public function test_a_cleared_from_stage_without_a_sequence_position_falls_back_to_completed(): void
    {
        $this->builderFile('Declared');
        $this->builderFile('Completed');
        $apart = $this->statusRow('Pending Editing');

        $open = $this->builderService()->query($this->builderFilters(['cleared_from_stage' => $apart->id]))->get();

        $this->assertCount(1, $open);
    }

    public function test_the_threshold_card_counts_over_and_unknown_aging(): void
    {
        $this->builderFile('Declared', ['doc_submission_date' => today()->subDays(40)->toDateString()]);
        $this->builderFile('Declared', ['doc_submission_date' => null]);

        $values = collect($this->card('over_threshold')['lines'])->pluck('value', 'label');

        $this->assertSame('1', $values[__('resources/report/strings.customs.lines.over')]);
        $this->assertSame('1', $values[__('resources/report/strings.customs.lines.no_basis')]);
    }

    public function test_the_delays_card_also_counts_open_ten_percent_files_and_there_are_exactly_four_cards(): void
    {
        $this->builderFile('10% Remaining');
        $this->builderFile('Completed');

        $values = collect($this->card('over_threshold')['lines'])->pluck('value', 'label');

        $this->assertSame('1', $values[__('resources/report/strings.customs.lines.ten_percent')]);
        $this->assertCount(4, $this->builderService()->summary($this->builderFilters()));
    }

    public function test_the_guarantees_card_counts_not_returned_and_of_them_cleared(): void
    {
        $this->builderFile('Completed', ['bank_guarantee_status_id' => $this->statusRow('Not Returned', Custom::TYPE_BANK_GUARANTEE_STATUS)->id]);
        $this->builderFile('Declared', ['bank_guarantee_status_id' => $this->statusRow('Not Returned', Custom::TYPE_BANK_GUARANTEE_STATUS)->id]);

        $values = collect($this->card('guarantees')['lines'])->pluck('value', 'label');

        $this->assertSame('2', $values[__('resources/report/strings.customs.lines.count')]);
        $this->assertSame('1', $values[__('resources/report/strings.customs.lines.of_completed')]);
    }

    public function test_days_in_customs_use_the_doc_submission_basis_and_flag_a_missing_basis(): void
    {
        $dated = $this->builderFile('Declared');
        $bare = $this->builderFile('Declared', ['doc_submission_date' => null]);

        $rows = $this->builderService()->query($this->builderFilters())->get()->keyBy('id');

        $this->assertSame(10, (int) $rows[$dated->id]->days_in_customs);
        $this->assertNull($rows[$bare->id]->days_in_customs);
        $this->assertEquals(1, $rows[$bare->id]->basis_missing);
    }

    public function test_the_arrival_basis_filter_switches_the_measured_date(): void
    {
        $shipment = $this->shipment(['warehouse_date' => today()->subDays(5)->toDateString(), 'eta' => today()->subDays(30)->toDateString()], order: $this->builderOrder);
        $this->builderFile('Declared', [], $shipment);

        $byWarehouse = $this->builderService()->query($this->builderFilters(['arrival_basis' => 'warehouse_date']))->first();
        $byEta = $this->builderService()->query($this->builderFilters(['arrival_basis' => 'eta']))->first();

        $this->assertSame(5, (int) $byWarehouse->days_in_customs);
        $this->assertSame(30, (int) $byEta->days_in_customs);
    }

    public function test_closed_files_are_hidden_unless_the_show_closed_filter_is_set(): void
    {
        $closed = $this->builderFile('Completed');

        $this->assertNotContains($closed->id, $this->builderService()->query($this->builderFilters())->pluck('id')->all());
        $this->assertContains($closed->id, $this->builderService()->query($this->builderFilters(['show_closed' => true]))->pluck('id')->all());
    }

    public function test_a_long_product_list_is_not_cut_off(): void
    {
        $file = $this->builderFile('Declared', [], $this->shipment(withItem: false, order: $this->builderOrder));
        $names = collect(range(1, 60))->map(fn (int $number): string => 'Long product name for the customs list number '.$number);
        $names->each(fn (string $name) => $this->item($file->shipment, Product::factory()->create(['english_name' => $name, 'name' => $name])));

        $text = (string) $this->builderService()->query($this->builderFilters())->get()->keyBy('id')[$file->id]->products_text;

        $this->assertGreaterThan(1024, strlen($text));
        $this->assertStringContainsString('number 60', $text);
        $this->assertSame(60, count(explode(', ', $text)));
    }

    public function test_products_and_weight_come_from_invoice_items_with_a_flagged_order_fallback(): void
    {
        $product = Product::factory()->create(['name' => 'سیم', 'english_name' => 'Cable']);
        $withItems = $this->builderFile('Declared', [], $this->shipment(withItem: false, order: $this->builderOrder));
        $this->item($withItems->shipment, $product, ['gross_weight' => 250]);

        $fallback = $this->builderFile('Declared', [], $this->shipment(withItem: false, order: $this->builderOrder));
        RegisteredOrderItem::factory()->create(['registered_order_id' => $this->builderOrder->id, 'product_id' => $product->id, 'gross_weight' => 80]);

        $rows = $this->builderService()->query($this->builderFilters())->get()->keyBy('id');

        $this->assertSame('Cable', $rows[$withItems->id]->products_text);
        $this->assertSame('invoice', $rows[$withItems->id]->weight_source);
        $this->assertSame(250.0, (float) $rows[$withItems->id]->total_weight);
        $this->assertSame('Cable', $rows[$fallback->id]->products_text);
        $this->assertSame('order', $rows[$fallback->id]->weight_source);
        $this->assertSame(80.0, (float) $rows[$fallback->id]->total_weight);
    }

    public function test_export_rows_use_exactly_the_exported_labels(): void
    {
        $this->builderFile('Declared');

        $row = collect($this->builderService()->rows($this->builderFilters()))->first();

        $this->assertSame(array_keys($this->builderService()->exportLabels()), array_keys($row));
    }

    public function test_the_row_query_count_is_constant(): void
    {
        $this->builderFile('Declared');
        $this->builderService()->query($this->builderFilters())->get();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->builderService()->query($this->builderFilters())->get();
        $one = count(DB::getQueryLog());

        collect(range(1, 5))->each(fn () => $this->builderFile('Declared'));
        DB::flushQueryLog();
        $this->builderService()->query($this->builderFilters())->get();
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($one, $many);
    }

    public function test_the_stage_chart_counts_follow_the_ordered_stages(): void
    {
        $this->builderFile('Declared');
        $this->builderFile('Declared');

        $chart = $this->builderService()->chartStage($this->builderFilters());

        $this->assertCount(count($chart['labels']), $chart['counts']);
        $this->assertSame(2, array_sum($chart['counts']));
    }

    public function test_the_month_chart_averages_days_per_clearance_month(): void
    {
        $this->builderFile('Completed', ['clearance_date' => '2026-01-15', 'doc_submission_date' => '2026-01-05']);
        $this->builderFile('Completed', ['clearance_date' => '2026-01-25', 'doc_submission_date' => '2026-01-10']);

        $chart = $this->builderService()->chartByMonth($this->builderFilters());

        $this->assertCount(1, $chart['labels']);
        $this->assertSame([12.5], $chart['averages']);
    }

    public function test_the_month_chart_labels_follow_the_active_calendar_without_changing_the_buckets(): void
    {
        $this->builderFile('Completed', ['clearance_date' => '2026-01-15', 'doc_submission_date' => '2026-01-05']);
        $this->builderFile('Completed', ['clearance_date' => '2026-03-25', 'doc_submission_date' => '2026-03-15']);

        session(['calendar_type' => 'gregorian']);
        $gregorian = $this->builderService()->chartByMonth($this->builderFilters());
        session(['calendar_type' => 'jalali']);
        $jalali = $this->builderService()->chartByMonth($this->builderFilters());
        session()->forget('calendar_type');

        $this->assertSame(['January 2026', 'March 2026'], $gregorian['labels']);
        $this->assertCount(2, $jalali['labels']);
        $this->assertStringContainsString('1404', $jalali['labels'][0]);
        $this->assertStringContainsString('1405', $jalali['labels'][1]);
        $this->assertNotSame($gregorian['labels'], $jalali['labels']);
        $this->assertSame([10.0, 10.0], $gregorian['averages']);
        $this->assertSame($gregorian['averages'], $jalali['averages']);
    }

    public function test_the_registry_holds_the_thirteen_customs_checks(): void
    {
        $checks = collect(app(CheckRegistry::class)->forReport('customs'));

        $this->assertCount(13, $checks);
        $this->assertSame($checks->count(), $checks->pluck('key')->unique()->count());
        $this->assertSame(2, $checks->where('severity', 'error')->count());
        $this->assertSame(10, $checks->where('severity', 'warning')->count());
        $this->assertSame(1, $checks->where('severity', 'info')->count());
        $this->assertSame(12, $checks->where('model', Custom::class)->count());
        $this->assertSame(1, $checks->where('model', Shipment::class)->count());
        $checks->each(function (Check $check): void {
            $this->assertContains('customs', $check->reports);
            $this->assertNull($check->systemFix);
        });
    }

    public function test_every_fix_points_at_a_real_tab_key_and_field(): void
    {
        foreach ([...CustomChecks::all(), ...ShipmentChecks::all()] as $check) {
            if (! in_array('customs', $check->reports, true)) {
                continue;
            }

            $this->assertNotSame($check->fix['tab'], __($check->fix['tab']));
            $fillable = $check->fix['resource'] === CustomResource::class
                ? (new Custom)->getFillable()
                : [...(new Shipment)->getFillable(), 'items'];
            $this->assertContains($check->fix['field'], $fillable);
        }
    }

    public function test_every_message_and_expected_text_is_translated_in_all_locales(): void
    {
        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);

            foreach ([...CustomChecks::all(), ...ShipmentChecks::all()] as $check) {
                if (! in_array('customs', $check->reports, true)) {
                    continue;
                }

                $this->assertNotSame($check->messageKey(), __($check->messageKey()));
                $this->assertNotSame($check->expectedKey(), __($check->expectedKey()));
            }
        }
    }

    #[DataProvider('keys')]
    public function test_an_offender_is_returned_and_a_compliant_file_is_not(string $key): void
    {
        $offender = $this->offending($key);
        $compliant = $this->compliant($key);

        $ids = $this->ids($key);

        $this->assertContains($offender->id, $ids);
        $this->assertNotContains($compliant->id, $ids);
    }

    #[DataProvider('keys')]
    public function test_the_registered_order_filter_narrows_the_result(string $key): void
    {
        $other = RegisteredOrder::factory()->create();
        $mine = $this->offending($key);
        $theirs = $this->offending($key, $other);

        $this->assertSame([$mine->id], $this->ids($key));
        $this->assertSame([$theirs->id], $this->ids($key, new ReportFilters(registeredOrderId: $other->id)));
    }

    #[DataProvider('keys')]
    public function test_offending_and_expected_texts_are_non_empty_strings(string $key): void
    {
        $check = $this->check($key);
        $offender = $this->offending($key);
        $record = ($check->query)(($check->model)::query(), new ReportFilters(registeredOrderId: $offender->registered_order_id ?? $offender->order?->id ?? $this->checksOrder->id))->first();

        $this->assertIsString(($check->offending)($record));
        $this->assertNotSame('', ($check->expected)($record));
    }

    public function test_every_shared_filter_narrows_the_result(): void
    {
        $key = 'custom.clearance_type_missing';
        $categoryA = Category::factory()->create();
        $productA = Product::factory()->create(['category_id' => $categoryA->id]);
        $productB = Product::factory()->create();
        $declared = $this->statusRow('Declared');
        $appraisal = $this->statusRow('Appraisal');
        $notReturned = $this->statusRow('Not Returned', Custom::TYPE_BANK_GUARANTEE_STATUS);
        $notCompleted = $this->statusRow('Not Completed', Custom::TYPE_COMMITMENT_STATUS);
        $a = $this->customsFile(['clearance_type' => null, 'doc_submission_date' => today()->subDays(20)->toDateString()]);
        $this->item($a->shipment, $productA);
        $b = $this->customsFile(['clearance_type' => null, 'doc_submission_date' => today()->subDays(5)->toDateString(), 'clearance_status_id' => $appraisal->id, 'bank_guarantee_status_id' => $notReturned->id, 'commitment_status_id' => $notCompleted->id]);
        $this->item($b->shipment, $productB);
        $order = $this->checksOrder->id;
        $elsewhere = RegisteredOrder::factory()->create(['seller_id' => Company::factory()->create()->id, 'buyer_id' => Company::factory()->create()->id]);
        $c = $this->customsFile(['clearance_type' => null], order: $elsewhere);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $this->ids($key));
        $this->assertSame([$c->id], $this->ids($key, new ReportFilters(sellerId: $elsewhere->seller_id)));
        $this->assertSame([$c->id], $this->ids($key, new ReportFilters(buyerId: $elsewhere->buyer_id)));
        $this->assertSame([$a->id], $this->ids($key, new ReportFilters(registeredOrderId: $order, clearanceStatusIds: [$declared->id])));
        $this->assertSame([$b->id], $this->ids($key, new ReportFilters(registeredOrderId: $order, bankGuaranteeStatusId: $notReturned->id, commitmentStatusId: $notCompleted->id)));
        $this->assertSame([$a->id], $this->ids($key, ReportFilters::fromArray(['registered_order_id' => $order, 'category_id' => $categoryA->id])));
        $this->assertSame([$b->id], $this->ids($key, new ReportFilters(registeredOrderId: $order, productId: $productB->id)));
        $this->assertSame([$b->id], $this->ids($key, ReportFilters::fromArray(['registered_order_id' => $order, 'date_from' => today()->subDays(10)->toDateString()])));
        $this->assertSame([$a->id], $this->ids($key, ReportFilters::fromArray(['registered_order_id' => $order, 'date_until' => today()->subDays(10)->toDateString()])));
    }

    public function test_the_product_filter_also_matches_through_the_order_items_fallback(): void
    {
        $key = 'custom.clearance_type_missing';
        $product = Product::factory()->create();
        $bare = $this->customsFile(['clearance_type' => null]);
        $this->item($bare->shipment, Product::factory()->create());
        $orderOnly = $this->customsFile(['clearance_type' => null], shipment: $this->shipment(withItem: false));
        RegisteredOrderItem::factory()->create(['registered_order_id' => $this->checksOrder->id, 'product_id' => $product->id]);

        $this->assertEqualsCanonicalizing([$bare->id, $orderOnly->id], $this->ids($key, new ReportFilters(registeredOrderId: $this->checksOrder->id, productId: $product->id)));
    }

    public function test_trashed_customs_are_never_offenders(): void
    {
        $key = 'custom.clearance_type_missing';
        $file = $this->customsFile(['clearance_type' => null]);
        $file->delete();

        $this->assertNotContains($file->id, $this->ids($key));
    }

    public function test_the_over_threshold_check_honors_the_stuck_days_filter_and_its_edge(): void
    {
        $key = 'custom.open_over_threshold';
        $edge = $this->customsFile(['doc_submission_date' => today()->subDays(30)->toDateString()]);
        $over = $this->customsFile(['doc_submission_date' => today()->subDays(31)->toDateString()]);
        $ancient = $this->customsFile(['doc_submission_date' => today()->subDays(61)->toDateString()]);

        $this->assertEqualsCanonicalizing([$over->id, $ancient->id], $this->ids($key));
        $this->assertSame([$ancient->id], $this->ids($key, new ReportFilters(registeredOrderId: $this->checksOrder->id, stuckDays: 60)));
    }

    public function test_the_over_threshold_expected_text_names_the_filter_threshold(): void
    {
        $this->customsFile(['doc_submission_date' => today()->subDays(31)->toDateString()]);
        $check = $this->check('custom.open_over_threshold');
        $filters = new ReportFilters(registeredOrderId: $this->checksOrder->id, stuckDays: 20);
        $record = ($check->query)(Custom::query(), $filters)->first();

        $this->assertStringContainsString('20', ($check->expected)($record, $filters));
        $this->assertStringContainsString('30', ($check->expected)($record));
    }

    public function test_cleared_before_arrival_reports_both_dates(): void
    {
        $file = $this->customsFile(['doc_submission_date' => '2026-01-10', 'clearance_date' => '2026-01-05']);
        $check = $this->check('custom.cleared_before_arrival');

        $found = ($check->query)(Custom::query(), new ReportFilters(registeredOrderId: $this->checksOrder->id))->first();

        $this->assertSame($file->id, $found->id);
        $this->assertStringContainsString(adaptiveDate('2026-01-05'), ($check->offending)($found));
        $this->assertStringContainsString(adaptiveDate('2026-01-10'), ($check->offending)($found));
    }

    public function test_shipment_not_arrived_is_cleared_by_a_warehouse_date_or_an_arrived_status(): void
    {
        $key = 'custom.shipment_not_arrived';
        $offender = $this->customsFile(shipment: $this->shipment(['warehouse_date' => null]));
        $byWarehouse = $this->customsFile();
        $tracking = Status::firstOrCreate(['english_type' => Shipment::TYPE_TRACKING_STATUS, 'english_name' => 'Arrived At Destination'], ['type' => Shipment::TYPE_TRACKING_STATUS, 'name' => 'Arrived At Destination']);
        $byTracking = $this->customsFile(shipment: $this->shipment(['warehouse_date' => null, 'shipment_status_id' => $tracking->id]));

        $ids = $this->ids($key);

        $this->assertContains($offender->id, $ids);
        $this->assertNotContains($byWarehouse->id, $ids);
        $this->assertNotContains($byTracking->id, $ids);
    }

    public function test_a_trashed_shipment_is_not_reported_as_not_arrived_and_unknown_statuses_do_not_count_as_arrived(): void
    {
        $key = 'custom.shipment_not_arrived';
        $trashedShipment = $this->shipment(['warehouse_date' => null]);
        $trashed = $this->customsFile(shipment: $trashedShipment);
        $trashedShipment->delete();
        $unknown = Status::firstOrCreate(['english_type' => Shipment::TYPE_TRACKING_STATUS, 'english_name' => 'Custom Hold '.uniqid()], ['type' => Shipment::TYPE_TRACKING_STATUS, 'name' => 'Custom Hold']);
        $held = $this->customsFile(shipment: $this->shipment(['warehouse_date' => null, 'shipment_status_id' => $unknown->id]));

        $ids = $this->ids($key);

        $this->assertNotContains($trashed->id, $ids);
        $this->assertContains($held->id, $ids);
    }

    public function test_a_shipment_past_arrival_is_not_reported_as_not_arrived(): void
    {
        $key = 'custom.shipment_not_arrived';
        $before = Status::firstOrCreate(['english_type' => Shipment::TYPE_TRACKING_STATUS, 'english_name' => 'In Transit'], ['type' => Shipment::TYPE_TRACKING_STATUS, 'name' => 'In Transit']);
        Status::firstOrCreate(['english_type' => Shipment::TYPE_TRACKING_STATUS, 'english_name' => 'Arrived At Destination'], ['type' => Shipment::TYPE_TRACKING_STATUS, 'name' => 'Arrived At Destination']);
        $later = Status::firstOrCreate(['english_type' => Shipment::TYPE_TRACKING_STATUS, 'english_name' => 'Delivered'], ['type' => Shipment::TYPE_TRACKING_STATUS, 'name' => 'Delivered']);
        $stillOnTheWay = $this->customsFile(shipment: $this->shipment(['warehouse_date' => null, 'shipment_status_id' => $before->id]));
        $delivered = $this->customsFile(shipment: $this->shipment(['warehouse_date' => null, 'shipment_status_id' => $later->id]));

        $ids = $this->ids($key);

        $this->assertContains($stillOnTheWay->id, $ids);
        $this->assertNotContains($delivered->id, $ids);
    }

    public function test_cleared_without_clearance_date_ignores_files_that_have_a_date(): void
    {
        $key = 'custom.cleared_without_clearance_date';
        $missing = $this->customsFile(['clearance_date' => null], 'Completed');
        $dated = $this->customsFile(['clearance_date' => today()->subDay()->toDateString()], 'Completed');

        $ids = $this->ids($key);

        $this->assertContains($missing->id, $ids);
        $this->assertNotContains($dated->id, $ids);
    }

    public function test_stuck_in_stage_excludes_cleared_statuses_and_uses_the_latest_history_row(): void
    {
        $key = 'custom.stuck_in_stage';
        $cleared = $this->customsFile([], 'Completed');
        $this->history($cleared, 40);
        $stuck = $this->customsFile();
        $this->history($stuck, 90);
        $this->history($stuck, 40);
        $recent = $this->customsFile();
        $this->history($recent, 5);

        $this->assertNotContains($cleared->id, $this->ids($key));
        $this->assertNotContains($recent->id, $this->ids($key));
        $this->assertContains($stuck->id, $this->ids($key));
    }

    public function test_stuck_in_stage_falls_back_to_updated_at_without_history_and_flags_it(): void
    {
        $file = $this->unhistoried($this->customsFile());
        DB::table('customs')->where('id', $file->id)->update(['updated_at' => now()->subDays(45)]);
        $check = $this->check('custom.stuck_in_stage');

        $found = ($check->query)(Custom::query(), new ReportFilters(registeredOrderId: $this->checksOrder->id))->first();

        $this->assertSame($file->id, $found->id);
        $this->assertStringContainsString('45', ($check->offending)($found));
        $this->assertStringContainsString(__('resources/report/strings.attention.no_history_note'), ($check->offending)($found));
    }

    public function test_stuck_in_stage_threshold_is_the_class_constant(): void
    {
        $key = 'custom.stuck_in_stage';
        $edge = $this->customsFile();
        $this->history($edge, CustomChecks::STUCK_DAYS);
        $over = $this->customsFile();
        $this->history($over, CustomChecks::STUCK_DAYS + 1);

        $this->assertSame([$over->id], $this->ids($key));
        $this->assertNotContains($edge->id, $this->ids($key));
    }

    public function test_count_queries_do_not_hydrate_rows(): void
    {
        $this->history($this->customsFile(), 40);
        $this->history($this->customsFile(), 40);
        $check = $this->check('custom.stuck_in_stage');

        $count = ($check->query)(Custom::query(), new ReportFilters(registeredOrderId: $this->checksOrder->id))->count();

        $this->assertSame(2, $count);
    }

    private function quickMix(): void
    {
        $notReturned = $this->statusRow('Not Returned', Custom::TYPE_BANK_GUARANTEE_STATUS)->id;
        $this->builderFile('Declared');
        $this->builderFile('Declared', ['doc_submission_date' => today()->subDays(45)->toDateString()]);
        $this->builderFile('Appraisal', ['doc_submission_date' => null]);
        $this->builderFile(null);
        $this->builderFile('Pending Editing');
        $this->builderFile('10% Remaining', ['bank_guarantee_status_id' => $notReturned]);
        $this->builderFile('Declared', ['bank_guarantee_status_id' => $notReturned]);
        $this->builderFile('Completed', ['bank_guarantee_status_id' => $notReturned, 'clearance_date' => today()->toDateString()]);
        $this->builderFile('Completed');
        $this->builderFile($this->statusRow('Stray '.uniqid())->english_name);
    }

    private function quickLines(?ReportFilters $filters = null): array
    {
        return collect($this->builderService()->summary($filters ?? $this->builderFilters()))
            ->flatMap(fn (array $card): array => $card['lines'])
            ->filter(fn (array $line): bool => isset($line['quick']))
            ->all();
    }

    public function test_every_quick_line_filters_the_table_to_exactly_the_rows_it_counts(): void
    {
        $this->quickMix();

        $lines = $this->quickLines();

        $this->assertGreaterThanOrEqual(8, count($lines));
        $this->assertGreaterThan(0, collect($lines)->filter(fn (array $line): bool => str_starts_with($line['quick'], 'stage_'))->count());

        foreach ($lines as $line) {
            $rows = $this->builderService()->query($this->builderFilters(['quick' => $line['quick']]))->get();

            $this->assertSame((int) $line['value'], $rows->count(), $line['quick']);
            $this->assertSame($rows->count(), $rows->pluck('id')->unique()->count(), $line['quick']);
        }
    }

    public function test_the_mixed_fixture_gives_the_expected_values_and_the_guarantee_lines_include_cleared_files(): void
    {
        $this->quickMix();

        $values = collect($this->quickLines())->pluck('value', 'quick');

        $this->assertSame('8', $values['open_files']);
        $this->assertSame('2', $values['no_status']);
        $this->assertSame('1', $values['over_threshold']);
        $this->assertSame('1', $values['no_basis']);
        $this->assertSame('1', $values['ten_percent']);
        $this->assertSame('3', $values['guarantees_not_returned']);
        $this->assertSame('1', $values['guarantees_cleared']);

        $cleared = $this->builderService()->query($this->builderFilters(['quick' => 'guarantees_cleared']))->get();
        $notReturned = $this->builderService()->query($this->builderFilters(['quick' => 'guarantees_not_returned']))->get();

        $this->assertSame([$this->statusRow('Completed')->id], $cleared->pluck('clearance_status_id')->unique()->all());
        $this->assertContains($this->statusRow('Completed')->id, $notReturned->pluck('clearance_status_id')->all());
    }

    public function test_the_quick_filter_keeps_open_scope_even_when_cleared_files_are_shown(): void
    {
        $this->quickMix();

        foreach ($this->quickLines() as $line) {
            $rows = $this->builderService()->query($this->builderFilters(['quick' => $line['quick'], 'show_closed' => true]))->get();

            $this->assertSame((int) $line['value'], $rows->count(), $line['quick']);
        }
    }

    public function test_an_unknown_or_malformed_quick_key_leaves_the_table_unchanged(): void
    {
        $this->quickMix();
        $plain = $this->builderService()->query($this->builderFilters())->pluck('id')->all();

        foreach (['nope', 'stage_', 'stage_x', 'STAGE_1'] as $key) {
            $this->assertEqualsCanonicalizing($plain, $this->builderService()->query($this->builderFilters(['quick' => $key]))->pluck('id')->all(), $key);
        }

        $this->assertCount(0, $this->builderService()->query($this->builderFilters(['quick' => 'stage_999999999']))->get());
    }

    public function test_quick_does_not_change_cards_charts_or_checks(): void
    {
        $this->quickMix();
        $plain = $this->builderFilters();
        $quick = $this->builderFilters(['quick' => 'no_status']);

        $this->assertSame($this->builderService()->summary($plain), $this->builderService()->summary($quick));
        $this->assertSame($this->builderService()->chartStage($plain), $this->builderService()->chartStage($quick));
        $this->assertSame($this->builderService()->chartByMonth($plain), $this->builderService()->chartByMonth($quick));
        $this->assertSame($this->builderService()->chartStage($plain)['counts'], $this->builderService()->chartStage($this->builderFilters(['quick' => 'guarantees_cleared']))['counts']);

        $check = $this->check('custom.open_over_threshold');
        $ids = fn (ReportFilters $filters): array => ($check->query)(Custom::query(), $filters)->get()->modelKeys();

        $this->assertSame($ids($plain), $ids($quick));
        $this->assertSame($plain->cacheKey(), $quick->cacheKey());
    }

    public function test_rows_honour_the_quick_filter(): void
    {
        $this->quickMix();

        $all = collect($this->builderService()->rows($this->builderFilters()))->count();
        $guarantees = collect($this->builderService()->rows($this->builderFilters(['quick' => 'guarantees_not_returned'])))->count();
        $none = collect($this->builderService()->rows($this->builderFilters(['quick' => 'no_status'])))->count();

        $this->assertSame(8, $all);
        $this->assertSame(3, $guarantees);
        $this->assertSame(2, $none);
    }

    public function test_the_stuck_threshold_drives_the_card_the_quick_rows_and_both_checks_at_a_non_default_value(): void
    {
        $this->builderFile('Declared');
        $this->builderFile('Declared', ['doc_submission_date' => today()->subDays(3)->toDateString()]);
        $stuck = $this->builderFile('Declared');
        $this->history($stuck, 10);
        $filters = $this->builderFilters(['stuck_days' => 5]);
        $over = (int) collect($this->builderService()->summary($filters))->firstWhere('key', 'over_threshold')['lines'][0]['value'];
        $rows = $this->builderService()->query($this->builderFilters(['stuck_days' => 5, 'quick' => 'over_threshold']))->get();
        $own = new ReportFilters(registeredOrderId: $this->builderOrder->id, stuckDays: 5);
        $default = new ReportFilters(registeredOrderId: $this->builderOrder->id);

        $this->assertSame(2, $over);
        $this->assertSame($over, $rows->count());
        $this->assertCount(2, $this->ids('custom.open_over_threshold', $own));
        $this->assertSame([], $this->ids('custom.open_over_threshold', $default));
        $this->assertSame([$stuck->id], $this->ids('custom.stuck_in_stage', $own));
        $this->assertSame([], $this->ids('custom.stuck_in_stage', $default));
        $this->assertStringContainsString('5', ($this->check('custom.stuck_in_stage')->expected)($stuck, $own));
        $this->assertStringContainsString('30', ($this->check('custom.stuck_in_stage')->expected)($stuck, $default));
    }

    public function test_the_aging_colour_warns_at_half_the_threshold_and_alarms_above_it(): void
    {
        $service = CustomsReportService::class;

        $this->assertSame('gray', $service::agingColor(null, 30));
        $this->assertSame('success', $service::agingColor(14, 30));
        $this->assertSame('warning', $service::agingColor(15, 30));
        $this->assertSame('warning', $service::agingColor(30, 30));
        $this->assertSame('danger', $service::agingColor(31, 30));
        $this->assertSame('success', $service::agingColor(2, 5));
        $this->assertSame('warning', $service::agingColor(3, 5));
        $this->assertSame('danger', $service::agingColor(6, 5));
    }

    public function test_each_date_basis_moves_the_range_and_undated_files_stay_unless_switched_off(): void
    {
        $order = $this->builderOrder;
        $a = $this->builderFile('Declared', ['doc_submission_date' => today()->subDays(20)->toDateString(), 'clearance_date' => today()->subDay()->toDateString()], $this->shipment(['warehouse_date' => today()->subDays(40)->toDateString(), 'eta' => today()->subDays(2)->toDateString()], order: $order));
        $b = $this->builderFile('Declared', ['doc_submission_date' => today()->subDays(3)->toDateString(), 'clearance_date' => today()->subDays(30)->toDateString()], $this->shipment(['warehouse_date' => today()->subDay()->toDateString(), 'eta' => today()->subDays(35)->toDateString()], order: $order));
        $none = $this->builderFile('Declared', ['doc_submission_date' => null, 'clearance_date' => null], $this->shipment(['warehouse_date' => null, 'eta' => null], order: $order));
        DB::table('customs')->where('id', $a->id)->update(['created_at' => now()->subDays(50)]);
        $from = today()->subDays(10)->toDateString();
        $ids = fn (array $extra): array => $this->builderService()->query($this->builderFilters(['date_from' => $from] + $extra))->pluck('customs.id')->all();

        $this->assertEqualsCanonicalizing([$b->id, $none->id], $ids([]));
        $this->assertEqualsCanonicalizing([$b->id, $none->id], $ids(['date_basis' => 'doc_submission_date']));
        $this->assertEqualsCanonicalizing([$a->id, $none->id], $ids(['date_basis' => 'clearance_date']));
        $this->assertEqualsCanonicalizing([$b->id, $none->id], $ids(['date_basis' => 'warehouse_date']));
        $this->assertEqualsCanonicalizing([$a->id, $none->id], $ids(['date_basis' => 'eta']));
        $this->assertEqualsCanonicalizing([$b->id, $none->id], $ids(['date_basis' => 'created_at']));
        $this->assertSame([$b->id], $ids(['include_undated' => false]));
        $this->assertSame([$a->id], $ids(['date_basis' => 'eta', 'include_undated' => false]));
        $this->assertEqualsCanonicalizing([$b->id, $none->id], $ids(['date_basis' => 'nonsense']));
        $this->assertCount(3, $this->builderService()->query($this->builderFilters())->get());
    }

    public function test_the_date_basis_also_scopes_the_shipment_quantity_check(): void
    {
        $key = 'shipment.customs_quantity_above_shipped';
        $shipment = $this->quantityOverShipped($this->checksOrder);
        $base = ['registered_order_id' => $this->checksOrder->id];

        $this->assertSame([$shipment->id], $this->ids($key, ReportFilters::fromArray($base + ['date_from' => today()->subDays(11)->toDateString()])));
        $this->assertSame([], $this->ids($key, ReportFilters::fromArray($base + ['date_from' => today()->subDays(5)->toDateString()])));
        $this->assertSame([], $this->ids($key, ReportFilters::fromArray($base + ['date_until' => today()->subDays(15)->toDateString()])));
        $this->assertSame([], $this->ids($key, ReportFilters::fromArray($base + ['date_from' => today()->subDays(11)->toDateString(), 'date_basis' => 'warehouse_date'])));
    }

    public function test_the_document_status_and_carrier_filters_narrow_rows_and_cards(): void
    {
        $doc = Status::firstOrCreate(['english_type' => Shipment::TYPE_DOC_STATUS, 'english_name' => 'Customs Doc Test'], ['type' => Shipment::TYPE_DOC_STATUS, 'name' => 'Customs Doc Test']);
        $carrier = Company::factory()->create();
        $withDoc = $this->builderFile('Declared', [], $this->shipment(['doc_status_id' => $doc->id], order: $this->builderOrder));
        $withCarrier = $this->builderFile('Declared', [], $this->shipment(['company_id' => $carrier->id, 'doc_status_id' => null], order: $this->builderOrder));
        $plain = $this->builderFile('Declared', [], $this->shipment(['doc_status_id' => null, 'company_id' => Company::factory()->create()->id], order: $this->builderOrder));
        $ids = fn (array $extra): array => $this->builderService()->query($this->builderFilters($extra))->pluck('customs.id')->all();

        $this->assertSame([$withDoc->id], $ids(['doc_status_id' => $doc->id]));
        $this->assertSame([$withCarrier->id], $ids(['company_id' => $carrier->id]));
        $this->assertEqualsCanonicalizing([$withDoc->id, $withCarrier->id, $plain->id], $ids([]));
        $this->assertSame('1', collect($this->builderService()->summary($this->builderFilters(['doc_status_id' => $doc->id])))->firstWhere('key', 'open')['lines'][0]['value']);

        $flagged = $this->customsFile(['declaration_no' => null], shipment: $this->shipment(['doc_status_id' => $doc->id]));
        $other = $this->customsFile(['declaration_no' => null], shipment: $this->shipment(['doc_status_id' => null]));

        $this->assertSame([$flagged->id], $this->ids('custom.missing_declaration_no', new ReportFilters(registeredOrderId: $this->checksOrder->id, docStatusId: $doc->id)));
        $this->assertEqualsCanonicalizing([$flagged->id, $other->id], $this->ids('custom.missing_declaration_no'));
    }

    public function test_the_guarantee_cleared_line_follows_the_cleared_from_stage_filter_and_a_note_line_follows(): void
    {
        $notReturned = $this->statusRow('Not Returned', Custom::TYPE_BANK_GUARANTEE_STATUS)->id;
        $this->builderFile('Completed', ['bank_guarantee_status_id' => $notReturned]);
        $this->builderFile('Exit Gate', ['bank_guarantee_status_id' => $notReturned]);
        $this->builderFile('Declared', ['bank_guarantee_status_id' => $notReturned]);
        $exitGate = $this->statusRow('Exit Gate')->id;

        $default = collect($this->builderService()->summary($this->builderFilters()))->firstWhere('key', 'guarantees')['lines'];
        $moved = collect($this->builderService()->summary($this->builderFilters(['cleared_from_stage' => $exitGate])))->firstWhere('key', 'guarantees')['lines'];
        $rows = $this->builderService()->query($this->builderFilters(['cleared_from_stage' => $exitGate, 'quick' => 'guarantees_cleared']))->get();

        $this->assertSame(['3', '1'], [$default[0]['value'], $default[1]['value']]);
        $this->assertSame(['3', '2'], [$moved[0]['value'], $moved[1]['value']]);
        $this->assertSame(2, $rows->count());
        $this->assertCount(3, $moved);
        $this->assertArrayNotHasKey('quick', $moved[2]);
        $this->assertSame(__('resources/report/strings.customs.lines.guarantee_note'), $moved[2]['label']);
    }

    public function test_seller_and_commitment_status_are_exported_and_the_group_sort_sql_follows_the_stage_order(): void
    {
        $seller = Company::factory()->seller()->create(['english_name' => 'Seller Export X', 'name' => 'Seller Export X']);
        $this->builderOrder->update(['seller_id' => $seller->id]);
        $file = $this->builderFile('Declared');
        $this->statusRow('Completed');

        $row = collect($this->builderService()->rows($this->builderFilters()))->first();

        $this->assertSame('Seller Export X', $row['seller']);
        $this->assertSame($file->commitmentStatus->getLocalizedNameAttribute(), $row['commitment_status']);
        $this->assertSame(__('resources/custom/strings.table.shipment'), $this->builderService()->exportLabels()['shipment_no']);
        $this->assertArrayHasKey('seller', $this->builderService()->exportLabels());
        $this->assertArrayHasKey('commitment_status', $this->builderService()->exportLabels());

        preg_match('/FIELD\(customs\.clearance_status_id, ([\d,]+)\)/', $this->builderService()->stageOrderSql(), $match);
        $ids = array_map('intval', explode(',', $match[1]));

        $this->assertLessThan(array_search($this->statusRow('Completed')->id, $ids, true), array_search($this->statusRow('Declared')->id, $ids, true));
        $this->assertStringContainsString('registered_orders.id = customs.registered_order_id', CustomsReportService::sellerNameSql());
    }

    private function unhistoried(Custom $file): Custom
    {
        StatusHistory::where('statusable_type', Custom::class)->where('statusable_id', $file->id)->delete();

        return $file;
    }
}
