<?php

namespace Tests\Feature\Services\Reports;

use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Custom;
use App\Models\EntityAttribute;
use App\Models\RegisteredOrder;
use App\Models\RegisteredOrderItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Status;
use App\Services\Calendar\Display\CalendarRange;
use App\Services\Reports\Builders\FxCommitmentsReportService;
use App\Services\Reports\Checks\Check;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Checks\FxCommitmentChecks;
use App\Services\Reports\Rules\FxRateService;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\FxCommitmentLedger;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Support\TransitLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FxCommitmentsReportTest extends TestCase
{
    private array $statuses = [];

    private Currency $usd;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        $this->usd = Currency::factory()->create();
        $this->company = Company::factory()->create();

        foreach ([
            [BankProfile::TYPE_BANK_PROFILE, ['Purchased', 'Received']],
            [Custom::TYPE_CLEARANCE_STATUS, ['Completed', 'Declared', 'Exit Gate']],
            [Custom::TYPE_COMMITMENT_STATUS, ['Completed', 'Not Completed']],
            [Shipment::TYPE_SHIPMENT_STATUS, ['Cancelled', 'Active']],
        ] as [$type, $names]) {
            foreach ($names as $name) {
                $this->statusRow($name, $type);
            }
        }

        FxRateService::flush();
        ReportStages::flush();
        CheckRegistry::invalidate();
    }

    protected function tearDown(): void
    {
        FxCommitmentLedger::$maxOrders = 2000;
        TransitLedger::flush();
        session()->forget('calendar_type');
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

    public static function checkKeys(): array
    {
        return array_map(fn (Check $check): array => [$check->key], FxCommitmentChecks::all());
    }

    private function service(): FxCommitmentsReportService
    {
        return app(FxCommitmentsReportService::class);
    }

    private function statusRow(string $name, string $type): Status
    {
        return $this->statuses["{$type}:{$name}"] ??= Status::firstOrCreate(
            ['english_type' => $type, 'english_name' => $name],
            ['type' => $type, 'name' => $name],
        );
    }

    private function order(?Currency $currency = null): RegisteredOrder
    {
        return RegisteredOrder::factory()->create(['currency_id' => ($currency ?? $this->usd)->id]);
    }

    private function profile(RegisteredOrder $order, ?float $equivalent, ?Currency $currency, array $extra = [], ?Company $company = null): BankProfile
    {
        return BankProfile::factory()->create($extra + [
            'registered_order_id' => $order->id,
            'company_id' => ($company ?? $this->company)->id,
            'bank_id' => null,
            'status_id' => $this->statusRow('Purchased', BankProfile::TYPE_BANK_PROFILE)->id,
            'requested_currency_id' => ($currency ?? $this->usd)->id,
            'requested_amount' => $equivalent ?? 1,
            'purchased_currency_id' => $currency?->id,
            'purchased_equivalent' => $equivalent,
            'commitment_payment_date' => null,
            'purchase_date' => null,
            'creation_date' => today()->subDays(100)->toDateString(),
        ]);
    }

    private function rateProfile(Currency $from, float $requested, Currency $to, float $equivalent): BankProfile
    {
        return BankProfile::factory()->create([
            'registered_order_id' => $this->order()->id,
            'status_id' => $this->statusRow('Purchased', BankProfile::TYPE_BANK_PROFILE)->id,
            'requested_currency_id' => $from->id,
            'requested_amount' => $requested,
            'purchased_currency_id' => $to->id,
            'purchased_equivalent' => $equivalent,
            'purchase_date' => today()->toDateString(),
        ]);
    }

    private function euro(): Currency
    {
        return Currency::query()->where('is_active', true)->whereRaw('LOWER(english_name) = ?', ['euro'])->orderBy('id')->first()
            ?? Currency::factory()->create(['english_name' => 'Euro']);
    }

    private function shipment(RegisteredOrder $order, ?float $grand, array $extra = []): Shipment
    {
        return Shipment::factory()->create($extra + [
            'registered_order_id' => $order->id,
            'status_id' => $this->statusRow('Active', Shipment::TYPE_SHIPMENT_STATUS)->id,
            'grand_total' => $grand,
            'shipped_quantity' => 10,
            'warehouse_date' => null,
        ]);
    }

    private function cleared(RegisteredOrder $order, ?float $grand, string $stage = 'Completed', ?Shipment $shipment = null, array $extra = []): Custom
    {
        return Custom::factory()->create($extra + [
            'registered_order_id' => $order->id,
            'shipment_id' => ($shipment ?? $this->shipment($order, $grand))->id,
            'clearance_status_id' => $this->statusRow($stage, Custom::TYPE_CLEARANCE_STATUS)->id,
            'bank_guarantee_status_id' => null,
            'commitment_status_id' => $this->statusRow('Completed', Custom::TYPE_COMMITMENT_STATUS)->id,
        ]);
    }

    private function filters(RegisteredOrder|array $scope, array $extra = []): ReportFilters
    {
        return ReportFilters::fromArray($extra + (is_array($scope) ? $scope : ['registered_order_id' => $scope->id]));
    }

    private function rows(ReportFilters $filters): Collection
    {
        return $this->service()->query($filters)->get()->keyBy('id');
    }

    private function line(ReportFilters $filters, string $card, string $label): ?array
    {
        $found = collect($this->service()->summary($filters))->firstWhere('key', $card);

        return collect($found['lines'])->first(fn (array $line): bool => str_starts_with($line['label'], $label));
    }

    private function number(?array $line): float
    {
        return (float) str_replace(',', '', $line['value'] ?? '0');
    }

    private function names(): string
    {
        return (string) $this->usd->getLocalizedNameAttribute();
    }

    public function test_a_clearance_is_shared_between_the_profiles_of_the_order_in_proportion(): void
    {
        $order = $this->order();
        $big = $this->profile($order, 600, $this->usd);
        $small = $this->profile($order, 400, $this->usd);
        $this->cleared($order, 250);

        $rows = $this->rows($this->filters($order));

        $this->assertEqualsWithDelta(150, $rows[$big->id]->res_share, 0.00001);
        $this->assertEqualsWithDelta(100, $rows[$small->id]->res_share, 0.00001);
        $this->assertEqualsWithDelta(450, $rows[$big->id]->rem_share, 0.00001);
        $this->assertEqualsWithDelta(300, $rows[$small->id]->rem_share, 0.00001);
        $this->assertEqualsWithDelta(750, $rows[$big->id]->ord_remaining, 0.00001);
        $this->assertEqualsWithDelta(250, $rows->sum('res_share'), 0.00001);
        $this->assertEqualsWithDelta(750, $rows->sum('rem_share'), 0.00001);
    }

    public function test_the_bank_filter_only_picks_listed_rows_and_the_order_totals_stay_unfiltered(): void
    {
        $order = $this->order();
        $bank = Bank::factory()->create();
        $listed = $this->profile($order, 600, $this->usd, ['bank_id' => $bank->id]);
        $this->profile($order, 400, $this->usd);
        $this->cleared($order, 250);

        $rows = $this->rows($this->filters($order, ['bank_id' => $bank->id]));

        $this->assertSame([$listed->id], $rows->keys()->all());
        $this->assertEqualsWithDelta(150, $rows[$listed->id]->res_share, 0.00001);
        $this->assertEqualsWithDelta(750, $rows[$listed->id]->ord_remaining, 0.00001);
    }

    public function test_a_clearance_in_another_currency_uses_the_implied_rate_and_never_a_guess(): void
    {
        $other = Currency::factory()->create();
        $order = $this->order($other);
        $profile = $this->profile($order, 1000, $this->usd);
        $this->cleared($order, 100);
        $filters = $this->filters($order);

        $missing = $this->rows($filters)[$profile->id];

        $this->assertEqualsWithDelta(0, $missing->res_share, 0.00001);
        $this->assertSame(FxCommitmentLedger::NO_RATE, (int) $missing->led_flags & FxCommitmentLedger::NO_RATE);
        $this->assertNotNull($this->line($filters, 'purchased', __('resources/report/strings.fx_commitments.lines.excluded')));

        $this->rateProfile($other, 100, $this->usd, 150);

        $converted = $this->rows($filters)[$profile->id];

        $this->assertEqualsWithDelta(150, $converted->res_share, 0.00001);
        $this->assertEqualsWithDelta(850, $converted->ord_remaining, 0.00001);
    }

    public function test_statuses_at_or_after_the_stage_clear_and_two_customs_on_one_shipment_count_once(): void
    {
        $order = $this->order();
        $profile = $this->profile($order, 1000, $this->usd);
        $this->cleared($order, 100, 'Declared');
        $shipment = $this->shipment($order, 300);
        $this->cleared($order, null, 'Completed', $shipment);
        $this->cleared($order, null, 'Completed', $shipment);
        $this->cleared($order, 50, 'Exit Gate');

        $default = $this->rows($this->filters($order))[$profile->id];
        $early = $this->rows($this->filters($order, ['cleared_from_stage' => $this->statusRow('Exit Gate', Custom::TYPE_CLEARANCE_STATUS)->id]))[$profile->id];

        $this->assertEqualsWithDelta(300, $default->res_share, 0.00001);
        $this->assertEqualsWithDelta(350, $early->res_share, 0.00001);
    }

    public function test_an_outlier_invoice_total_is_excluded_and_flagged_until_the_toggle_is_on(): void
    {
        $order = $this->order();
        $profile = $this->profile($order, 1000, $this->usd);
        $shipment = $this->shipment($order, 200000000000);
        ShipmentItem::factory()->create(['shipment_id' => $shipment->id, 'quantity' => 1, 'unit_price' => 100, 'total_amount' => 100]);
        $this->cleared($order, null, 'Completed', $shipment);

        $excluded = $this->rows($this->filters($order))[$profile->id];
        $included = $this->rows($this->filters($order, ['include_outliers' => true, 'commitment_state' => 'resolved']))[$profile->id];

        $this->assertEqualsWithDelta(0, $excluded->res_share, 0.00001);
        $this->assertSame(FxCommitmentLedger::OUTLIER, (int) $excluded->led_flags & FxCommitmentLedger::OUTLIER);
        $this->assertEqualsWithDelta(200000000000, $included->res_share, 0.1);
    }

    public function test_a_currency_mismatch_and_a_cancelled_shipment_are_excluded_until_their_toggles_are_on(): void
    {
        $z = Currency::factory()->create();
        $this->rateProfile($z, 100, $this->usd, 200);
        $order = $this->order();
        $profile = $this->profile($order, 1000, $this->usd);
        $mismatch = $this->shipment($order, 50);
        EntityAttribute::factory()->forEntity($mismatch)->create(['key' => 'commercial_invoice', 'value' => json_encode(['currency' => $z->english_name])]);
        $this->cleared($order, null, 'Completed', $mismatch);

        $off = $this->rows($this->filters($order))[$profile->id];
        $on = $this->rows($this->filters($order, ['include_flagged_invoices' => true]))[$profile->id];

        $this->assertEqualsWithDelta(0, $off->res_share, 0.00001);
        $this->assertSame(FxCommitmentLedger::MISMATCH, (int) $off->led_flags & FxCommitmentLedger::MISMATCH);
        $this->assertEqualsWithDelta(100, $on->res_share, 0.00001);

        $cancelledOrder = $this->order();
        $cancelledProfile = $this->profile($cancelledOrder, 1000, $this->usd);
        $this->cleared($cancelledOrder, 80, 'Completed', $this->shipment($cancelledOrder, 80, ['status_id' => $this->statusRow('Cancelled', Shipment::TYPE_SHIPMENT_STATUS)->id]));

        $voidOff = $this->rows($this->filters($cancelledOrder))[$cancelledProfile->id];
        $voidOn = $this->rows($this->filters($cancelledOrder, ['include_void' => true]))[$cancelledProfile->id];

        $this->assertEqualsWithDelta(0, $voidOff->res_share, 0.00001);
        $this->assertEqualsWithDelta(80, $voidOn->res_share, 0.00001);
    }

    public function test_no_invoice_resolves_nothing_until_the_order_amount_fallback_is_on_and_then_it_is_flagged_estimated(): void
    {
        $order = $this->order();
        $profile = $this->profile($order, 1000, $this->usd);
        RegisteredOrderItem::factory()->create(['registered_order_id' => $order->id, 'quantity' => 20, 'unit_price' => 50, 'line_total' => 1000]);
        $this->cleared($order, null);

        $off = $this->rows($this->filters($order))[$profile->id];
        $on = $this->rows($this->filters($order, ['fallback_order_amount' => true]))[$profile->id];

        $this->assertEqualsWithDelta(0, $off->res_share, 0.00001);
        $this->assertSame(FxCommitmentLedger::NO_INVOICE, (int) $off->led_flags & FxCommitmentLedger::NO_INVOICE);
        $this->assertEqualsWithDelta(500, $on->res_share, 0.00001);
        $this->assertSame(FxCommitmentLedger::ESTIMATED, (int) $on->led_flags & FxCommitmentLedger::ESTIMATED);
    }

    public function test_orders_that_cannot_be_worked_out_are_not_computable_and_never_guessed(): void
    {
        $z = Currency::factory()->create();
        $mixed = $this->order();
        $mixedUsd = $this->profile($mixed, 500, $this->usd);
        $mixedZ = $this->profile($mixed, 500, $z);
        $zero = $this->profile($this->order(), 0, $this->usd);
        $gone = $this->order();
        $orphan = $this->profile($gone, 100, $this->usd);
        $gone->delete();
        $this->cleared($mixed, 100);

        foreach ([$mixed->id => [$mixedUsd, $mixedZ], $zero->registered_order_id => [$zero], $gone->id => [$orphan]] as $orderId => $profiles) {
            $rows = $this->rows($this->filters(['registered_order_id' => $orderId, 'commitment_state' => 'not_computable']));

            foreach ($profiles as $profile) {
                $this->assertSame('not_computable', $rows[$profile->id]->state);
                $this->assertNull($rows[$profile->id]->res_share);
                $this->assertNull($rows[$profile->id]->rem_share);
            }
        }

        $this->assertCount(0, $this->rows($this->filters($mixed)));
        $this->assertSame(1, (int) $this->rows($this->filters(['registered_order_id' => $mixed->id, 'commitment_state' => 'not_computable']))[$mixedUsd->id]->is_mixed);
    }

    public function test_the_overdue_boundary_follows_the_late_grace_and_the_due_soon_window_edge_is_inclusive(): void
    {
        $order = $this->order();
        $dates = ['yesterday' => -1, 'today' => 0, 'minus3' => -3, 'minus4' => -4, 'plus30' => 30, 'plus31' => 31];
        $profiles = collect($dates)->map(fn (int $days): BankProfile => $this->profile($order, 100, $this->usd, ['commitment_payment_date' => today()->addDays($days)->toDateString()]));

        $state = fn (array $extra): array => $this->rows($this->filters($order, $extra + ['commitment_state' => null]))->mapWithKeys(fn ($row): array => [$row->id => $row->state])->all();
        $strict = $state([]);
        $graced = $state(['th_late_grace' => 3]);

        $this->assertSame('overdue', $strict[$profiles['yesterday']->id]);
        $this->assertSame('open', $strict[$profiles['today']->id]);
        $this->assertSame('open', $graced[$profiles['yesterday']->id]);
        $this->assertSame('open', $graced[$profiles['minus3']->id]);
        $this->assertSame('overdue', $graced[$profiles['minus4']->id]);

        $due = $this->rows($this->filters($order, ['quick' => 'due_soon']))->keys()->all();

        $this->assertContains($profiles['today']->id, $due);
        $this->assertContains($profiles['plus30']->id, $due);
        $this->assertNotContains($profiles['plus31']->id, $due);
        $this->assertNotContains($profiles['yesterday']->id, $due);
    }

    public function test_the_remainder_floors_at_zero_the_excess_is_shown_and_the_tolerance_is_respected(): void
    {
        $order = $this->order();
        $profile = $this->profile($order, 1000, $this->usd);
        $this->cleared($order, 1200);
        $filters = $this->filters($order, ['commitment_state' => 'resolved']);
        $row = $this->rows($filters)[$profile->id];
        $excess = $this->line($filters, 'remaining', __('resources/report/strings.fx_commitments.lines.excess'));

        $this->assertEqualsWithDelta(0, $row->ord_remaining, 0.00001);
        $this->assertEqualsWithDelta(0, $row->rem_share, 0.00001);
        $this->assertSame('resolved', $row->state);
        $this->assertEqualsWithDelta(200, $this->number($excess), 0.001);
        $this->assertContains($order->id, app(FxCommitmentLedger::class)->overResolvedOrderIds($this->filters($order)));
        $this->assertNotContains($order->id, app(FxCommitmentLedger::class)->overResolvedOrderIds($this->filters($order, ['th_exceed_pct' => 50])));
    }

    private function mixedFixture(): Company
    {
        $company = Company::factory()->create();
        $z = Currency::factory()->create();
        $partial = $this->order();
        $this->profile($partial, 600, $this->usd, ['commitment_payment_date' => today()->addDays(60)->toDateString()], $company);
        $this->profile($partial, 400, $this->usd, ['commitment_payment_date' => today()->addDays(60)->toDateString()], $company);
        $this->cleared($partial, 250);
        $done = $this->order();
        $this->profile($done, 500, $this->usd, ['commitment_payment_date' => today()->addDays(60)->toDateString()], $company);
        $this->cleared($done, 500);
        $this->profile($this->order(), 300, $this->usd, ['commitment_payment_date' => today()->subDay()->toDateString()], $company);
        $this->profile($this->order(), 200, $this->usd, ['commitment_payment_date' => today()->addDays(5)->toDateString()], $company);
        $this->profile($this->order(), 100, $this->usd, [], $company);
        $mixed = $this->order();
        $this->profile($mixed, 50, $this->usd, [], $company);
        $this->profile($mixed, 50, $z, [], $company);

        return $company;
    }

    public function test_every_clickable_line_equals_the_filtered_row_count_and_money_lines_are_not_clickable(): void
    {
        $company = $this->mixedFixture();
        $filters = $this->filters(['company_id' => $company->id]);
        $quickLines = 0;

        foreach ($this->service()->summary($filters) as $card) {
            foreach ($card['lines'] as $line) {
                if (! isset($line['quick'])) {
                    continue;
                }

                $quickLines++;
                $this->assertSame((int) $line['value'], $this->service()->query($this->filters(['company_id' => $company->id, 'quick' => $line['quick']]))->count(), $line['quick']);
            }
        }

        $this->assertSame(8, $quickLines);
        $this->assertCount(4, $this->service()->summary($filters));
        $this->assertSame(8, $this->service()->query($this->filters(['company_id' => $company->id, 'quick' => 'profiles']))->count());
        $this->assertSame(5, $this->service()->query($filters)->count());
    }

    public function test_both_charts_add_up_to_the_remaining_card_in_both_amount_modes(): void
    {
        $company = $this->mixedFixture();
        $this->rateProfile($this->usd, 100, $this->euro(), 90);
        $purchased = $this->filters(['company_id' => $company->id]);
        $eur = $this->filters(['company_id' => $company->id, 'amount_in' => 'eur']);
        $remaining = $this->number($this->line($purchased, 'remaining', $this->names()));
        $remainingEur = $this->number($this->line($eur, 'remaining', __('resources/report/strings.fx_commitments.lines.eur_total')));
        $total = fn (array $chart): float => collect($chart['datasets'])->sum(fn (array $set): float => array_sum($set['data']));

        $this->assertEqualsWithDelta(1350, $remaining, 0.01);
        $this->assertEqualsWithDelta(1215, $remainingEur, 0.01);
        $this->assertEqualsWithDelta($remaining, $total($this->service()->chartByMonth($purchased)), 0.01);
        $this->assertEqualsWithDelta($remaining, $total($this->service()->chartByBank($purchased)), 0.01);
        $this->assertEqualsWithDelta($remainingEur, $total($this->service()->chartByMonth($eur)), 0.01);
        $this->assertEqualsWithDelta($remainingEur, $total($this->service()->chartByBank($eur)), 0.01);

        $month = $this->service()->chartByMonth($purchased);

        $this->assertEqualsWithDelta(300, $month['datasets'][0]['data'][0], 0.01);
        $this->assertEqualsWithDelta(100, $month['datasets'][0]['data'][count($month['labels']) - 1], 0.01);
        $this->assertCount(1, $this->service()->chartByMonth($eur)['datasets']);
    }

    #[DataProvider('calendars')]
    public function test_the_month_buckets_follow_the_calendar_at_the_month_boundary(bool $jalali): void
    {
        session(['calendar_type' => $jalali ? 'jalali' : 'gregorian']);
        $range = CalendarRange::forMonth(today()->toDateString(), $jalali);
        $order = $this->order();
        $this->profile($order, 100, $this->usd, ['commitment_payment_date' => $range->end->toDateString()]);
        $this->profile($order, 200, $this->usd, ['commitment_payment_date' => $range->end->copy()->addDay()->toDateString()]);

        $chart = $this->service()->chartByMonth($this->filters($order));

        $this->assertSame($range->label(), $chart['labels'][1]);
        $this->assertEqualsWithDelta(100, $chart['datasets'][0]['data'][1], 0.01);
        $this->assertEqualsWithDelta(200, $chart['datasets'][0]['data'][2], 0.01);
    }

    public static function calendars(): array
    {
        return ['gregorian' => [false], 'jalali' => [true]];
    }

    public function test_the_csv_rows_and_labels_mirror_the_table(): void
    {
        $order = $this->order();
        $profile = $this->profile($order, 600, $this->usd, ['commitment_payment_date' => today()->subDay()->toDateString()]);
        $this->cleared($order, 250);
        $filters = $this->filters($order);

        $rows = collect($this->service()->rows($filters))->all();

        $this->assertCount($this->service()->query($filters)->count(), $rows);
        $this->assertSame(array_keys($this->service()->exportLabels()), array_keys($rows[0]));
        $this->assertEqualsWithDelta(250, $rows[0]['resolved'], 0.00001);
        $this->assertSame($profile->bp_number, $rows[0]['bp_number']);
        $this->assertSame($order->ro_number, $rows[0]['ro_number']);
        $this->assertSame(__('resources/report/strings.fx_commitments.states.overdue'), $rows[0]['state']);
    }

    public function test_trashed_profiles_customs_and_shipments_are_left_out(): void
    {
        $order = $this->order();
        $kept = $this->profile($order, 600, $this->usd);
        $trashed = $this->profile($order, 400, $this->usd);
        $custom = $this->cleared($order, 250);
        $filters = $this->filters($order);

        $this->assertEqualsWithDelta(150, $this->rows($filters)[$kept->id]->res_share, 0.00001);

        $trashed->delete();

        $this->assertEqualsWithDelta(250, $this->rows($filters)[$kept->id]->res_share, 0.00001);

        $custom->delete();

        $this->assertEqualsWithDelta(0, $this->rows($filters)[$kept->id]->res_share, 0.00001);

        $custom->restore();
        Shipment::find($custom->shipment_id)->delete();

        $this->assertEqualsWithDelta(0, $this->rows($filters)[$kept->id]->res_share, 0.00001);
        $this->assertContains($custom->id, $this->offenders('custom.cleared_without_shipment', $filters));
    }

    public function test_the_ledger_cache_key_moves_with_every_ledger_field_and_only_those(): void
    {
        $ledger = app(FxCommitmentLedger::class);
        $base = $ledger->key(ReportFilters::fromArray([]));
        $stage = $this->statusRow('Exit Gate', Custom::TYPE_CLEARANCE_STATUS)->id;

        foreach ([
            ['cleared_from_stage' => $stage],
            ['include_void' => true],
            ['include_flagged_invoices' => true],
            ['include_outliers' => true],
            ['fallback_order_amount' => true],
            ['value_basis' => 'items_total'],
            ['th_outlier_factor' => 7],
            ['registered_order_id' => $this->order()->id],
            ['rate_basis' => 'order'],
        ] as $change) {
            $this->assertNotSame($base, $ledger->key(ReportFilters::fromArray($change)), json_encode($change));
        }

        foreach ([
            ['bank_id' => 5],
            ['company_id' => 5],
            ['date_from' => '2026-01-01'],
            ['date_until' => '2026-12-31'],
            ['date_basis' => 'purchase_date'],
            ['amount_in' => 'eur'],
            ['th_commit_due' => 10],
            ['th_late_grace' => 5],
            ['th_exceed_pct' => 9],
            ['purchased_currency_id' => 5],
            ['supply_source' => 'export'],
            ['status_id' => [5]],
            ['commitment_state' => 'overdue'],
            ['commitment_status_id' => 5],
            ['quick' => 'overdue'],
        ] as $change) {
            $this->assertSame($base, $ledger->key(ReportFilters::fromArray($change)), json_encode($change));
        }
    }

    private function invoiceHeader(Shipment $shipment, string $currency): EntityAttribute
    {
        return EntityAttribute::factory()->forEntity($shipment)->create(['key' => 'commercial_invoice', 'value' => ['currency' => $currency]]);
    }

    private function uniqueCurrency(): Currency
    {
        $name = 'UQ'.substr(md5(uniqid('', true)), 0, 10);

        return Currency::factory()->create(['name' => $name, 'english_name' => $name]);
    }

    private function inferredShipment(RegisteredOrder $order): Shipment
    {
        $shipment = $this->shipment($order, 100);
        $this->invoiceHeader($shipment, 'ZZZ unknown');
        $this->cleared($order, null, 'Completed', $shipment);

        return $shipment;
    }

    private function rateBasisOrder(Currency $z, bool $ownPair = true): array
    {
        $a = $this->uniqueCurrency();
        $order = $this->order($a);
        $own = $ownPair ? $this->profile($order, 150, $a, ['requested_currency_id' => $z->id, 'requested_amount' => 100]) : $this->profile($order, 150, $a);
        $this->profile($order, 850, $a);
        $shipment = $this->shipment($order, 100);
        $this->invoiceHeader($shipment, $z->english_name);
        $this->cleared($order, null, 'Completed', $shipment);
        $this->rateProfile($z, 100, $a, 200);

        return [$order, $own];
    }

    public function test_an_unreadable_invoice_currency_uses_the_order_currency_visibly_and_matches_the_executive_report(): void
    {
        $euro = $this->euro();
        $this->rateProfile($this->usd, 100, $euro, 100);
        $order = $this->order();
        $profile = $this->profile($order, 1000, $this->usd);
        $shipment = $this->shipment($order, 100, ['exit_date' => today()->subDays(3)->toDateString(), 'eta' => today()->addDays(9)->toDateString(), 'shipment_status_id' => null]);
        $this->invoiceHeader($shipment, 'ZZZ unknown');
        $this->cleared($order, null, 'Completed', $shipment);
        TransitLedger::flush();
        $filters = $this->filters($order);
        $row = $this->rows($filters)[$profile->id];
        $transit = TransitLedger::rows($filters)['rows'][$shipment->id];

        $this->assertEqualsWithDelta(100, $row->res_share, 0.00001);
        $this->assertSame(FxCommitmentLedger::CURRENCY_INFERRED, (int) $row->led_flags & FxCommitmentLedger::CURRENCY_INFERRED);
        $this->assertSame(0, $transit['reason']);
        $this->assertSame(TransitLedger::FLAG_CURRENCY_INFERRED, $transit['flags']);
        $this->assertSame([$shipment->id], $this->offenders('shipment.cleared_currency_inferred', $filters));
        $this->assertContains(__('resources/report/strings.fx_commitments.flags.currency_inferred'), explode(', ', (string) $this->service()->cell($row, 'flags')));
    }

    public function test_editing_the_invoice_header_refreshes_the_ledger_at_once(): void
    {
        $own = $this->uniqueCurrency();
        $z = $this->uniqueCurrency();
        $this->rateProfile($z, 100, $own, 200);
        $order = $this->order($own);
        $profile = $this->profile($order, 1000, $own);
        $shipment = $this->shipment($order, 50);
        $header = $this->invoiceHeader($shipment, $z->english_name);
        $this->cleared($order, null, 'Completed', $shipment);
        $filters = $this->filters($order);

        $this->assertSame(FxCommitmentLedger::MISMATCH, (int) $this->rows($filters)[$profile->id]->led_flags & FxCommitmentLedger::MISMATCH);

        $header->update(['value' => ['currency' => $own->english_name]]);

        $this->assertSame(0, (int) $this->rows($filters)[$profile->id]->led_flags & FxCommitmentLedger::MISMATCH);
        $this->assertEqualsWithDelta(50, $this->rows($filters)[$profile->id]->res_share, 0.00001);
    }

    public function test_the_ledger_is_one_instance_per_request(): void
    {
        $this->assertSame(app(FxCommitmentLedger::class), app(FxCommitmentLedger::class));
    }

    public function test_the_order_rate_basis_uses_the_orders_own_profiles_and_the_default_stays_the_pair_rate(): void
    {
        $z = $this->uniqueCurrency();
        [$order, $own] = $this->rateBasisOrder($z);
        $options = ['include_flagged_invoices' => true];

        $pair = $this->rows($this->filters($order, $options))[$own->id];
        $byOrder = $this->rows($this->filters($order, $options + ['rate_basis' => 'order']))[$own->id];

        $this->assertEqualsWithDelta(200, $pair->ord_resolved, 0.00001);
        $this->assertEqualsWithDelta(800, $pair->ord_remaining, 0.00001);
        $this->assertEqualsWithDelta(150, $byOrder->ord_resolved, 0.00001);
        $this->assertEqualsWithDelta(850, $byOrder->ord_remaining, 0.00001);
        $this->assertEqualsWithDelta(0.15 * 150, $byOrder->res_share, 0.00001);
        $this->assertSame(0, (int) $byOrder->led_flags & FxCommitmentLedger::NO_RATE);
    }

    public function test_the_order_rate_basis_excludes_and_flags_an_order_without_its_own_pair_and_never_falls_back(): void
    {
        $z = $this->uniqueCurrency();
        [$order, $own] = $this->rateBasisOrder($z, false);
        $options = ['include_flagged_invoices' => true];

        $pair = $this->rows($this->filters($order, $options))[$own->id];
        $byOrder = $this->rows($this->filters($order, $options + ['rate_basis' => 'order']))[$own->id];

        $this->assertEqualsWithDelta(200, $pair->ord_resolved, 0.00001);
        $this->assertEqualsWithDelta(0, $byOrder->ord_resolved, 0.00001);
        $this->assertSame(FxCommitmentLedger::NO_RATE, (int) $byOrder->led_flags & FxCommitmentLedger::NO_RATE);
        $this->assertContains($order->id, app(FxCommitmentLedger::class)->orderIdsWithFlag($this->filters($order, $options + ['rate_basis' => 'order']), FxCommitmentLedger::NO_RATE));
    }

    public function test_the_same_currency_converts_at_one_under_the_order_rate_basis(): void
    {
        $order = $this->order();
        $profile = $this->profile($order, 1000, $this->usd);
        $this->cleared($order, 300);
        $filters = $this->filters($order, ['rate_basis' => 'order']);

        $this->assertEqualsWithDelta(300, $this->rows($filters)[$profile->id]->res_share, 0.00001);
    }

    public function test_cards_quick_lines_and_charts_reconcile_in_both_rate_modes(): void
    {
        $company = $this->mixedFixture();

        foreach (['pair', 'order'] as $basis) {
            $filters = $this->filters(['company_id' => $company->id], ['rate_basis' => $basis]);

            foreach ($this->service()->summary($filters) as $card) {
                foreach ($card['lines'] as $line) {
                    if (isset($line['quick'])) {
                        $this->assertSame((int) $line['value'], $this->service()->query($this->filters(['company_id' => $company->id], ['rate_basis' => $basis, 'quick' => $line['quick']]))->count(), "{$basis}:{$line['quick']}");
                    }
                }
            }

            $remaining = $this->number($this->line($filters, 'remaining', $this->names()));
            $month = collect($this->service()->chartByMonth($filters)['datasets'])->sum(fn (array $set): float => array_sum($set['data']));
            $bank = collect($this->service()->chartByBank($filters)['datasets'])->sum(fn (array $set): float => array_sum($set['data']));

            $this->assertEqualsWithDelta($remaining, $month, 0.01, $basis);
            $this->assertEqualsWithDelta($remaining, $bank, 0.01, $basis);
        }
    }

    public function test_more_orders_than_the_cap_show_a_notice_and_no_numbers_instead_of_truncating(): void
    {
        $first = $this->order();
        $second = $this->order();
        $this->profile($first, 100, $this->usd);
        $this->profile($second, 100, $this->usd);
        $this->cleared($first, 40);
        $this->cleared($second, 40);
        FxCommitmentLedger::$maxOrders = 1;
        $filters = $this->filters(['registered_order_id' => null]);
        $ledger = app(FxCommitmentLedger::class);

        $this->assertTrue($ledger->summary($filters)['oversize']);
        $this->assertSame([], $ledger->orderIds($filters));
        $this->assertStringContainsString('where 1 = 0', $ledger->literalSql($filters));

        $cards = collect($this->service()->summary($filters))->keyBy('key');
        $notice = __('resources/report/strings.fx_commitments.lines.oversize');

        foreach (['resolved', 'remaining', 'overdue'] as $key) {
            $this->assertSame([$notice], array_column($cards[$key]['lines'], 'label'), $key);
            $this->assertArrayNotHasKey('quick', $cards[$key]['lines'][0]);
        }

        $this->assertCount(0, $this->service()->query($filters)->get());
        $this->assertSame([], $this->service()->chartByMonth($filters)['datasets']);
        $this->assertSame([], $this->service()->chartByBank($filters)['datasets']);
        $this->assertFalse($ledger->summary($this->filters($first))['oversize']);
    }

    public function test_an_unknown_quick_key_keeps_the_default_overdue_and_open_restriction(): void
    {
        $open = $this->order();
        $openProfile = $this->profile($open, 100, $this->usd);
        $done = $this->order();
        $doneProfile = $this->profile($done, 100, $this->usd);
        $this->cleared($done, 100);
        $scope = ['company_id' => $this->company->id, 'registered_order_id' => null];

        $listed = fn (array $extra): array => $this->service()->query(ReportFilters::fromArray($extra + $scope))->get()->modelKeys();

        $this->assertContains($openProfile->id, $listed(['quick' => 'queued']));
        $this->assertNotContains($doneProfile->id, $listed(['quick' => 'queued']));
        $this->assertContains($doneProfile->id, $listed(['quick' => 'queued', 'commitment_state' => 'resolved']));
    }

    public function test_a_date_that_has_passed_is_never_due_soon_even_with_a_late_grace(): void
    {
        $order = $this->order();
        $yesterday = $this->profile($order, 100, $this->usd, ['commitment_payment_date' => today()->subDay()->toDateString()]);
        $today = $this->profile($order, 100, $this->usd, ['commitment_payment_date' => today()->toDateString()]);
        $filters = $this->filters($order, ['th_late_grace' => 3]);

        $states = $this->rows($this->filters($order, ['th_late_grace' => 3, 'commitment_state' => 'open']));
        $due = $this->rows($this->filters($order, ['th_late_grace' => 3, 'quick' => 'due_soon']))->keys()->all();
        $card = (int) $this->line($filters, 'overdue', __('resources/report/strings.fx_commitments.lines.due_soon', ['days' => 30]))['value'];

        $this->assertSame('open', $states[$yesterday->id]->state);
        $this->assertNotContains($yesterday->id, $due);
        $this->assertContains($today->id, $due);
        $this->assertSame(1, $card);
    }

    public function test_the_euro_remaining_column_converts_the_remaining_share(): void
    {
        $euro = $this->euro();
        $this->rateProfile($this->usd, 100, $euro, 90);
        $order = $this->order();
        $profile = $this->profile($order, 600, $this->usd);
        $this->profile($order, 400, $this->usd);
        $this->cleared($order, 250);
        $row = $this->rows($this->filters($order))[$profile->id];

        $this->assertEqualsWithDelta(450 * 0.9, $this->service()->cell($row, 'eur_remaining'), 0.00001);
        $this->assertArrayHasKey('eur_remaining', $this->service()->exportLabels());
        $this->assertArrayHasKey('eur_remaining', collect($this->service()->rows($this->filters($order)))->first());
    }

    public function test_the_page_numbers_cost_a_bounded_number_of_queries(): void
    {
        $company = $this->mixedFixture();
        $filters = $this->filters(['company_id' => $company->id]);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->service()->summary($filters);
        $this->service()->query($filters)->get();
        $this->service()->chartByMonth($filters);
        $this->service()->chartByBank($filters);

        $this->assertLessThanOrEqual(90, count(DB::getQueryLog()));
    }

    public function test_the_check_list_is_complete_unique_and_registered_for_the_report(): void
    {
        $checks = collect(FxCommitmentChecks::all());
        $registered = app(CheckRegistry::class)->forReport('fx_commitments');

        $this->assertCount(21, $checks);
        $this->assertSame($checks->pluck('key')->unique()->count(), $checks->count());
        $this->assertSame($checks->pluck('key')->sort()->values()->all(), $registered->pluck('key')->sort()->values()->all());
        $this->assertSame(1, app(CheckRegistry::class)->all()->pluck('key')->countBy()->get('custom.cleared_without_invoice'));
        $this->assertSame(1, app(CheckRegistry::class)->all()->pluck('key')->countBy()->get('shipment.invoice_total_outlier'));

        foreach ($checks as $check) {
            $this->assertContains($check->severity, ['error', 'warning', 'info']);
            $this->assertNotSame($check->messageKey(), __($check->messageKey()), $check->key);
            $this->assertNotSame($check->expectedKey(), __($check->expectedKey()), $check->key);
        }
    }

    private function offenders(string $key, ReportFilters $filters): array
    {
        $check = collect(FxCommitmentChecks::all())->firstWhere('key', $key);

        return ($check->query)($check->model::query(), $filters)->get()->modelKeys();
    }

    /**
     * @return array{0: RegisteredOrder, 1: Model}
     */
    private function scenario(string $key): array
    {
        $order = $this->order();
        $other = Currency::factory()->create();
        $model = match ($key) {
            'bank_profile.commitment_without_equivalent' => $this->profile($order, null, $this->usd),
            'bank_profile.commitment_without_currency' => $this->profile($order, 100, null),
            'bank_profile.commitment_without_order' => tap($this->profile($order, 100, $this->usd), fn () => $order->delete()),
            'bank_profile.commitment_missing_date' => $this->profile($order, 100, $this->usd),
            'bank_profile.commitment_overdue' => $this->profile($order, 100, $this->usd, ['commitment_payment_date' => today()->subDay()->toDateString()]),
            'bank_profile.commitment_due_soon' => $this->profile($order, 100, $this->usd, ['commitment_payment_date' => today()->addDays(5)->toDateString()]),
            'bank_profile.commitment_date_order_invalid' => $this->profile($order, 100, $this->usd, ['commitment_payment_date' => today()->subDays(10)->toDateString(), 'purchase_date' => today()->toDateString()]),
            'bank_profile.fx_rate_missing' => $this->noRateScenario($order, $other),
            'custom.cleared_without_invoice' => $this->withProfile($order, fn () => $this->cleared($order, null)),
            'custom.cleared_without_shipment' => $this->withProfile($order, fn () => tap($this->cleared($order, 100), fn (Custom $custom) => Shipment::find($custom->shipment_id)->delete())),
            'custom.cleared_without_order' => $this->withProfile($order, fn () => tap($this->cleared($order, 100), fn () => $order->delete())),
            'custom.order_differs_from_shipment' => $this->withProfile($order, fn () => $this->cleared($order, null, 'Completed', $this->shipment($this->order(), 100))),
            'custom.cleared_on_void_shipment' => $this->withProfile($order, fn () => $this->cleared($order, null, 'Completed', $this->shipment($order, 100, ['status_id' => $this->statusRow('Cancelled', Shipment::TYPE_SHIPMENT_STATUS)->id]))),
            'custom.commitment_not_completed_after_clearance' => $this->withProfile($order, fn () => $this->cleared($order, 100, extra: ['commitment_status_id' => $this->statusRow('Not Completed', Custom::TYPE_COMMITMENT_STATUS)->id])),
            'shipment.invoice_currency_mismatch' => $this->withProfile($order, fn () => $this->headerShipment($order, $other)),
            'shipment.cleared_currency_unknown' => $this->withProfile($order, fn () => $this->unknownCurrencyShipment($order)),
            'shipment.cleared_currency_inferred' => $this->withProfile($order, fn () => $this->inferredShipment($order)),
            'shipment.invoice_total_outlier' => $this->withProfile($order, fn () => $this->outlierShipment($order)),
            'registered_order.resolved_exceeds_purchased' => tap($order, fn () => $this->withProfile($order, fn () => $this->cleared($order, 1200))),
            'registered_order.mixed_purchase_currency' => tap($order, function () use ($order, $other): void {
                $this->profile($order, 500, $this->usd);
                $this->profile($order, 500, $other);
            }),
            'registered_order.cleared_without_purchase' => tap($order, fn () => $this->cleared($order, 100)),
        };

        return [$order, $model];
    }

    private function withProfile(RegisteredOrder $order, callable $make): Model
    {
        $this->profile($order, 1000, $this->usd);

        return $make();
    }

    private function noRateScenario(RegisteredOrder $order, Currency $other): BankProfile
    {
        $order->update(['currency_id' => $other->id]);
        $profile = $this->profile($order, 1000, $this->usd);
        $this->cleared($order, 100);

        return $profile;
    }

    private function headerShipment(RegisteredOrder $order, Currency $other): Shipment
    {
        $shipment = $this->shipment($order, 50);
        EntityAttribute::factory()->forEntity($shipment)->create(['key' => 'commercial_invoice', 'value' => json_encode(['currency' => $other->english_name])]);
        $this->cleared($order, null, 'Completed', $shipment);

        return $shipment;
    }

    private function unknownCurrencyShipment(RegisteredOrder $order): Shipment
    {
        $shipment = $this->shipment($order, 100);
        $this->cleared($order, null, 'Completed', $shipment);
        $order->delete();

        return $shipment;
    }

    private function outlierShipment(RegisteredOrder $order): Shipment
    {
        $shipment = $this->shipment($order, 200000000000);
        ShipmentItem::factory()->create(['shipment_id' => $shipment->id, 'quantity' => 1, 'unit_price' => 100, 'total_amount' => 100]);
        $this->cleared($order, null, 'Completed', $shipment);

        return $shipment;
    }

    #[DataProvider('checkKeys')]
    public function test_each_check_lists_its_offender_and_leaves_a_clean_order_alone(string $key): void
    {
        [$order, $model] = $this->scenario($key);
        $clean = $this->order();
        $this->profile($clean, 1000, $this->usd, ['commitment_payment_date' => today()->addDays(90)->toDateString()]);
        $cleanCustom = $this->cleared($clean, 100);

        if ($key === 'shipment.cleared_currency_inferred') {
            $this->invoiceHeader(Shipment::find($cleanCustom->shipment_id), $this->usd->english_name);
        }

        $this->assertContains($model->getKey(), $this->offenders($key, $this->filters($order)), $key);
        $this->assertSame([], $this->offenders($key, $this->filters($clean)), $key);
    }
}
