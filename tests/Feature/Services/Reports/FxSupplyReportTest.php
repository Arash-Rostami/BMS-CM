<?php

namespace Tests\Feature\Services\Reports;

use App\Filament\Resources\BankProfileResource;
use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Company;
use App\Models\Currency;
use App\Models\RegisteredOrder;
use App\Models\Status;
use App\Models\StatusHistory;
use App\Services\Reports\Builders\FxSupplyReportService;
use App\Services\Reports\Checks\BankProfileChecks;
use App\Services\Reports\Checks\Check;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Rules\FxRateService;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\ReportFilters;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FxSupplyReportTest extends TestCase
{
    private array $checksStatuses = [];

    private RegisteredOrder $builderOrder;

    private RegisteredOrder $checksOrder;

    private Currency $usd;

    private Bank $checksBank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        FxRateService::flush();
        ReportStages::flush();
        $this->builderOrder = RegisteredOrder::factory()->create();
        $this->checksOrder = RegisteredOrder::factory()->create();
        $this->usd = Currency::factory()->create();
        $this->checksBank = Bank::factory()->create();
    }

    protected function tearDown(): void
    {
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

    public static function keys(): array
    {
        return array_map(fn (Check $check) => [$check->key], BankProfileChecks::all());
    }

    private function builderService(): FxSupplyReportService
    {
        return app(FxSupplyReportService::class);
    }

    private function builderFilters(): ReportFilters
    {
        return ReportFilters::fromArray(['registered_order_id' => $this->builderOrder->id]);
    }

    private function builderStatus(string $name): Status
    {
        return Status::factory()->create([
            'type' => BankProfile::TYPE_BANK_PROFILE,
            'english_type' => BankProfile::TYPE_BANK_PROFILE,
            'name' => $name,
            'english_name' => $name,
        ]);
    }

    private function builderProfile(?string $status, float $amount = 1000, array $extra = []): BankProfile
    {
        return BankProfile::factory()->create($extra + [
            'registered_order_id' => $this->builderOrder->id,
            'status_id' => $status ? $this->builderStatus($status)->id : null,
            'requested_currency_id' => $this->usd->id,
            'requested_amount' => $amount,
        ]);
    }

    private function card(string $key): array
    {
        return collect($this->builderService()->summary($this->builderFilters()))->firstWhere('key', $key);
    }

    private function checksStatus(string $name, string $type = BankProfile::TYPE_BANK_PROFILE): Status
    {
        return $this->checksStatuses["{$type}:{$name}"] ??= Status::factory()->create([
            'type' => $type,
            'english_type' => $type,
            'name' => $name,
            'english_name' => $name,
        ]);
    }

    private function checksProfile(array $attributes = [], string $status = 'Purchased', ?RegisteredOrder $order = null): BankProfile
    {
        return BankProfile::factory()->create($attributes + [
            'registered_order_id' => ($order ?? $this->checksOrder)->id,
            'status_id' => $this->checksStatus($status)->id,
            'requested_amount' => 1000,
            'purchased_equivalent' => 900,
            'exchange_rate' => 5,
            'creation_date' => '2026-01-01',
            'allocation_date' => '2026-01-02',
            'purchase_date' => '2026-01-03',
            'payment_due_date' => today()->addDays(200)->toDateString(),
            'bank_id' => $this->checksBank->id,
        ]);
    }

    private function offending(string $key, ?RegisteredOrder $order = null): BankProfile
    {
        return match ($key) {
            'bank_profile.purchased_without_equivalent' => $this->checksProfile(['purchased_equivalent' => 0], order: $order),
            'bank_profile.purchased_without_currency' => $this->checksProfile(['purchased_currency_id' => null], order: $order),
            'bank_profile.purchased_without_purchase_date' => $this->checksProfile(['purchase_date' => null], order: $order),
            'bank_profile.allocated_without_allocation_date' => $this->checksProfile(['allocation_date' => null], 'Allocated', $order),
            'bank_profile.missing_exchange_rate' => $this->checksProfile(['exchange_rate' => 0], order: $order),
            'bank_profile.requested_amount_zero' => $this->checksProfile(['requested_amount' => 0], order: $order),
            'bank_profile.stuck_in_stage' => $this->aged($this->checksProfile(order: $order), 40),
            'bank_profile.payment_due_soon' => $this->checksProfile(['payment_due_date' => today()->addDays(5)->toDateString()], 'Allocated', $order),
            'bank_profile.payment_overdue' => $this->checksProfile(['payment_due_date' => today()->subDay()->toDateString()], 'Allocated', $order),
            'bank_profile.status_invalid_type' => $this->checksProfile(['status_id' => $this->checksStatus('Other Kind', 'Foreign Type '.uniqid())->id], order: $order),
            'bank_profile.status_missing' => $this->checksProfile(['status_id' => null], order: $order),
            'bank_profile.no_status_history' => $this->unhistoried($this->checksProfile(order: $order)),
            'bank_profile.date_order_invalid' => $this->checksProfile(['allocation_date' => '2025-12-01'], order: $order),
            'bank_profile.missing_payment_due_date' => $this->checksProfile(['payment_due_date' => null], order: $order),
            'bank_profile.supplied_without_bank' => $this->checksProfile(['bank_id' => null], order: $order),
        };
    }

    private function aged(BankProfile $profile, int $days): BankProfile
    {
        StatusHistory::where('statusable_type', BankProfile::class)
            ->where('statusable_id', $profile->id)
            ->update(['created_at' => now()->subDays($days)]);

        return $profile;
    }

    private function unhistoried(BankProfile $profile): BankProfile
    {
        StatusHistory::where('statusable_type', BankProfile::class)->where('statusable_id', $profile->id)->delete();

        return $profile;
    }

    private function check(string $key): Check
    {
        return collect(BankProfileChecks::all())->firstWhere('key', $key);
    }

    private function ids(string $key, ?ReportFilters $filters = null): array
    {
        $check = $this->check($key);
        $filters ??= new ReportFilters(registeredOrderId: $this->checksOrder->id);

        return ($check->query)(BankProfile::query(), $filters)->get()->modelKeys();
    }

    public function test_queued_and_allocated_cards_group_amounts_by_currency_and_exclude_unrated_euro(): void
    {
        $this->builderProfile('Awaiting Supply', 1000);
        $this->builderProfile('Ready for Allocation', 500);
        $this->builderProfile('Allocated', 700);

        $queued = $this->card('queued');
        $values = collect($queued['lines'])->pluck('value', 'label');

        $this->assertSame('2', $values[__('resources/report/strings.fx_supply.lines.count')]);
        $this->assertSame(delimiter(1500), $values[$this->usd->getLocalizedNameAttribute()]);
        $this->assertTrue($values->has(__('resources/report/strings.fx_supply.lines.excluded')));
        $this->assertSame(delimiter(700), collect($this->card('allocated')['lines'])->pluck('value', 'label')[$this->usd->getLocalizedNameAttribute()]);
    }

    public function test_purchased_this_month_counts_only_supplied_profiles_dated_in_the_month(): void
    {
        $this->builderProfile('Purchased', 100, ['purchased_currency_id' => $this->usd->id, 'purchased_equivalent' => 90, 'purchase_date' => today()->toDateString()]);
        $this->builderProfile('Purchased', 100, ['purchased_currency_id' => $this->usd->id, 'purchased_equivalent' => 90, 'purchase_date' => today()->subMonths(3)->toDateString()]);

        $lines = collect($this->card('purchased_month')['lines'])->pluck('value', 'label');

        $this->assertSame('1', $lines[__('resources/report/strings.fx_supply.lines.count')]);
    }

    public function test_choosing_a_finished_status_lists_those_profiles_while_show_finished_is_off(): void
    {
        $purchased = $this->builderProfile('Purchased', 100);
        $received = $this->builderProfile('Received', 100);
        $open = $this->builderProfile('Awaiting Supply', 100);
        $ids = fn (array $extra = []): array => $this->builderService()->query(ReportFilters::fromArray(['registered_order_id' => $this->builderOrder->id] + $extra))->pluck('bank_profiles.id')->all();
        $label = __('resources/report/strings.fx_supply.lines.finished_hidden');
        $labels = fn (array $extra = []): array => collect($this->builderService()->summary(ReportFilters::fromArray(['registered_order_id' => $this->builderOrder->id] + $extra)))->flatMap(fn (array $card): array => $card['lines'])->pluck('label')->all();

        $this->assertSame([$open->id], $ids());
        $this->assertSame([$purchased->id], $ids(['status_id' => [$purchased->status_id]]));
        $this->assertEqualsCanonicalizing([$purchased->id, $open->id], $ids(['status_id' => [$purchased->status_id, $open->status_id]]));
        $this->assertSame([$open->id], $ids(['status_id' => [$open->status_id]]));
        $this->assertNotContains($label, $labels(['status_id' => [$received->status_id]]));
        $this->assertContains($label, $labels(['status_id' => [$open->status_id]]));
    }

    public function test_purchased_this_month_honours_the_jalali_month_end_boundary(): void
    {
        $profile = fn (string $date): BankProfile => $this->builderProfile('Purchased', 100, ['purchased_currency_id' => $this->usd->id, 'purchased_equivalent' => 90, 'purchase_date' => $date]);
        $first = $profile('2026-03-21');
        $last = $profile('2026-04-20');
        $next = $profile('2026-04-21');
        $before = $profile('2026-03-20');
        $endGregorian = $profile('2026-04-30');
        $afterGregorian = $profile('2026-05-01');
        $ids = fn (): array => $this->builderService()->query($this->quickFilters('purchased_month'))->pluck('bank_profiles.id')->all();

        try {
            \Illuminate\Support\Carbon::setTestNow('2026-04-10 12:00:00');
            session(['calendar_type' => 'jalali']);
            $jalali = $ids();
            session(['calendar_type' => 'gregorian']);
            $gregorian = $ids();
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
            session()->forget('calendar_type');
        }

        $this->assertEqualsCanonicalizing([$first->id, $last->id], $jalali);
        $this->assertNotContains($next->id, $jalali);
        $this->assertNotContains($before->id, $jalali);
        $this->assertEqualsCanonicalizing([$last->id, $next->id, $endGregorian->id], $gregorian);
        $this->assertNotContains($afterGregorian->id, $gregorian);
        $this->assertNotContains($first->id, $gregorian);
    }

    private function quickFilters(?string $quick): ReportFilters
    {
        return ReportFilters::fromArray(['registered_order_id' => $this->builderOrder->id, 'quick' => $quick]);
    }

    private function quickFixtures(): Currency
    {
        $eur = Currency::factory()->create();
        $month = today()->toDateString();
        $old = today()->subMonths(3)->toDateString();
        $this->builderProfile('Awaiting Supply', 1000);
        $this->builderProfile('Ready for Allocation', 500, ['requested_currency_id' => $eur->id]);
        $this->builderProfile('Awaiting Bank Review', 250);
        $this->builderProfile('Allocated', 700);
        $this->builderProfile('Allocated', 300, ['requested_currency_id' => $eur->id]);
        $this->builderProfile('Purchased', 100, ['purchased_currency_id' => $eur->id, 'purchased_equivalent' => 90, 'purchase_date' => $month]);
        $this->builderProfile('Received', 100, ['purchased_currency_id' => $this->usd->id, 'purchased_equivalent' => 40, 'purchase_date' => $month]);
        $this->builderProfile('Purchased', 100, ['purchased_currency_id' => $this->usd->id, 'purchased_equivalent' => 25, 'purchase_date' => $month]);
        $this->builderProfile('Purchased', 100, ['purchased_currency_id' => $this->usd->id, 'purchased_equivalent' => 999, 'purchase_date' => $old]);
        $this->builderProfile('Purchased', 100, ['purchased_currency_id' => null, 'purchased_equivalent' => 7, 'purchase_date' => $month]);
        $this->builderProfile(null, 5);

        return $eur;
    }

    public function test_every_quick_line_filters_the_table_to_exactly_the_rows_it_counts(): void
    {
        $this->quickFixtures();
        $asked = 0;

        foreach ($this->builderService()->summary($this->builderFilters()) as $card) {
            foreach ($card['lines'] as $line) {
                if (! isset($line['quick'])) {
                    continue;
                }

                $asked++;
                $rows = $this->builderService()->query($this->quickFilters($line['quick']))->get();

                if (! str_contains($line['quick'], '_currency_')) {
                    $this->assertSame((int) $line['value'], $rows->count(), $line['quick']);

                    continue;
                }

                $column = $card['key'] === 'purchased_month' ? 'purchased_equivalent' : 'requested_amount';
                $this->assertSame($line['value'], delimiter($rows->sum($column)), $line['quick']);
            }
        }

        $this->assertSame(12, $asked);
    }

    public function test_only_row_set_lines_carry_a_quick_key(): void
    {
        $this->quickFixtures();
        $labels = [
            __('resources/report/strings.fx_supply.lines.eur_total'),
            __('resources/report/strings.fx_supply.lines.excluded'),
            __('resources/report/strings.fx_supply.lines.creation_to_allocation'),
            __('resources/report/strings.fx_supply.lines.allocation_to_purchase'),
        ];

        foreach ($this->builderService()->summary($this->builderFilters()) as $card) {
            foreach ($card['lines'] as $line) {
                $this->assertFalse(isset($line['quick']) && in_array($line['label'], $labels, true), $line['label']);
            }
        }

        $this->assertCount(2, collect($this->card('queued')['lines'])->filter(fn (array $line) => isset($line['quick']) && str_contains($line['quick'], '_currency_')));
    }

    public function test_unknown_malformed_or_missing_quick_keys_leave_the_table_unchanged(): void
    {
        $this->quickFixtures();
        $all = $this->builderService()->query($this->quickFilters(null))->count();

        foreach (['nonsense', 'queued_currency_x', 'queued_currency_', 'average_days', 'queued_currency_1;drop'] as $key) {
            $this->assertSame($all, $this->builderService()->query($this->quickFilters($key))->count(), $key);
        }

        $this->assertSame(0, $this->builderService()->query($this->quickFilters('queued_currency_999999999'))->count());
    }

    public function test_quick_does_not_change_cards_charts_or_checks(): void
    {
        $this->quickFixtures();
        $quick = $this->quickFilters('allocated_currency_'.$this->usd->id);

        $this->assertSame($this->builderService()->summary($this->builderFilters()), $this->builderService()->summary($quick));
        $this->assertSame($this->builderService()->chartFunnel($this->builderFilters()), $this->builderService()->chartFunnel($quick));
        $this->assertSame($this->builderService()->chartByBank($this->builderFilters()), $this->builderService()->chartByBank($quick));
        $this->assertSame(
            collect(BankProfileChecks::all())->map(fn (Check $check) => ($check->query)(BankProfile::query(), $this->builderFilters())->count())->all(),
            collect(BankProfileChecks::all())->map(fn (Check $check) => ($check->query)(BankProfile::query(), $quick)->count())->all(),
        );
    }

    public function test_export_rows_honour_the_quick_filter(): void
    {
        $this->quickFixtures();

        $rows = collect($this->builderService()->rows($this->quickFilters('allocated')));

        $this->assertCount(2, $rows);
        $this->assertSame(['Allocated'], $rows->pluck('status')->unique()->all());
        $this->assertCount(6, $this->builderService()->rows($this->quickFilters(null)));
        $this->assertCount(11, $this->builderService()->rows(ReportFilters::fromArray(['registered_order_id' => $this->builderOrder->id, 'show_closed' => true])));
    }

    public function test_average_days_use_only_profiles_with_both_dates(): void
    {
        $this->builderProfile('Allocated', 100, ['creation_date' => '2026-01-01', 'allocation_date' => '2026-01-11', 'purchase_date' => '2026-01-16']);
        $this->builderProfile('Allocated', 100, ['creation_date' => '2026-01-01', 'allocation_date' => null, 'purchase_date' => null]);

        $lines = collect($this->card('timing_payments')['lines'])->take(2)->pluck('value');

        $this->assertSame(['10', '5'], $lines->all());
    }

    public function test_profiles_without_status_are_listed_and_do_not_break_the_summary(): void
    {
        $profile = $this->builderProfile(null);

        $this->assertTrue($this->builderService()->query($this->builderFilters())->pluck('bank_profiles.id')->contains($profile->id));
        $this->assertCount(4, $this->builderService()->summary($this->builderFilters()));
    }

    public function test_days_in_status_falls_back_to_updated_at_and_is_flagged_without_history(): void
    {
        $profile = $this->builderProfile('Allocated');
        StatusHistory::where('statusable_type', BankProfile::class)->where('statusable_id', $profile->id)->delete();
        DB::table('bank_profiles')->where('id', $profile->id)->update(['updated_at' => today()->subDays(12)]);

        $row = $this->builderService()->query($this->builderFilters())->first();

        $this->assertSame(12, (int) $row->days_in_status);
        $this->assertEquals(1, $row->without_history);
    }

    public function test_funnel_counts_follow_the_ordered_stages(): void
    {
        $this->builderProfile('Awaiting Supply');
        $this->builderProfile('Awaiting Supply');

        $funnel = $this->builderService()->chartFunnel($this->builderFilters());

        $this->assertCount(count($funnel['labels']), $funnel['counts']);
        $this->assertSame(2, array_sum($funnel['counts']));
    }

    public function test_by_bank_chart_has_a_dataset_per_currency(): void
    {
        $this->builderProfile('Awaiting Supply', 1000);

        $chart = $this->builderService()->chartByBank($this->builderFilters());

        $this->assertCount(1, $chart['datasets']);
        $this->assertSame([1000.0], $chart['datasets'][0]['data']);
    }

    public function test_export_rows_use_exactly_the_exported_labels(): void
    {
        $this->builderProfile('Allocated');

        $row = collect($this->builderService()->rows($this->builderFilters()))->first();

        $this->assertSame(array_keys($this->builderService()->exportLabels()), array_keys($row));
    }

    public function test_the_row_query_count_is_constant(): void
    {
        $this->builderProfile('Allocated');
        $this->builderService()->query($this->builderFilters())->get();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->builderService()->query($this->builderFilters())->get();
        $one = count(DB::getQueryLog());

        collect(range(1, 5))->each(fn () => $this->builderProfile('Allocated'));
        DB::flushQueryLog();
        $this->builderService()->query($this->builderFilters())->get();
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($one, $many);
    }

    public function test_the_registry_holds_the_fifteen_bank_profile_checks_for_the_fx_supply_report(): void
    {
        $checks = collect(BankProfileChecks::all());

        $this->assertCount(15, $checks);
        $this->assertSame($checks->count(), $checks->pluck('key')->unique()->count());
        $this->assertSame(['error', 'error', 'warning', 'warning', 'warning', 'error', 'warning', 'warning', 'error', 'error', 'error', 'info', 'error', 'warning', 'error'], $checks->pluck('severity')->all());
        $checks->each(fn (Check $check) => $this->assertSame(['fx_supply'], $check->reports));
        $checks->each(fn (Check $check) => $this->assertSame(BankProfile::class, $check->model));
        $checks->each(fn (Check $check) => $this->assertNull($check->systemFix));
    }

    public function test_every_fix_points_at_a_real_tab_key_and_a_bank_profile_column(): void
    {
        foreach (BankProfileChecks::all() as $check) {
            $this->assertSame(BankProfileResource::class, $check->fix['resource']);
            $this->assertNotSame($check->fix['tab'], __($check->fix['tab']));
            $this->assertContains($check->fix['field'], (new BankProfile)->getFillable());
        }
    }

    public function test_every_message_and_expected_text_is_translated_in_all_locales(): void
    {
        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);

            foreach (BankProfileChecks::all() as $check) {
                $this->assertNotSame($check->messageKey(), __($check->messageKey()));
                $this->assertNotSame($check->expectedKey(), __($check->expectedKey()));
            }
        }
    }

    #[DataProvider('keys')]
    public function test_an_offender_is_returned_and_a_compliant_profile_is_not(string $key): void
    {
        $offender = $this->offending($key);
        $compliant = $this->checksProfile();

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
        $profile = ($check->query)(BankProfile::query(), new ReportFilters(registeredOrderId: $this->offending($key)->registered_order_id))->first();

        $this->assertIsString(($check->offending)($profile));
        $this->assertNotSame('', ($check->expected)($profile));
    }

    public function test_every_shared_filter_narrows_the_result(): void
    {
        $key = 'bank_profile.requested_amount_zero';
        [$bankA, $bankB] = Bank::factory()->count(2)->create();
        [$companyA, $companyB] = Company::factory()->count(2)->create();
        [$currencyA, $currencyB] = Currency::factory()->count(2)->create();
        $statusA = $this->checksStatus('Purchased');
        $statusB = $this->checksStatus('Allocated');
        $a = $this->checksProfile(['requested_amount' => 0, 'bank_id' => $bankA->id, 'company_id' => $companyA->id, 'requested_currency_id' => $currencyA->id, 'supply_source' => 'alpha', 'creation_date' => '2026-01-10']);
        $b = $this->checksProfile(['requested_amount' => 0, 'bank_id' => $bankB->id, 'company_id' => $companyB->id, 'requested_currency_id' => $currencyB->id, 'supply_source' => 'beta', 'creation_date' => '2026-03-10'], 'Allocated');
        $order = $this->checksOrder->id;

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $this->ids($key));
        $this->assertSame([$a->id], $this->ids($key, new ReportFilters(registeredOrderId: $order, bankId: $bankA->id)));
        $this->assertSame([$b->id], $this->ids($key, new ReportFilters(registeredOrderId: $order, companyId: $companyB->id)));
        $this->assertSame([$a->id], $this->ids($key, new ReportFilters(registeredOrderId: $order, currencyId: $currencyA->id)));
        $this->assertSame([$b->id], $this->ids($key, new ReportFilters(registeredOrderId: $order, supplySource: 'beta')));
        $this->assertSame([$b->id], $this->ids($key, new ReportFilters(registeredOrderId: $order, statusIds: [$statusB->id])));
        $this->assertSame([$a->id], $this->ids($key, new ReportFilters(registeredOrderId: $order, statusIds: [$statusA->id])));
        $this->assertSame([$b->id], $this->ids($key, ReportFilters::fromArray(['registered_order_id' => $order, 'date_from' => '2026-02-01'])));
        $this->assertSame([$a->id], $this->ids($key, ReportFilters::fromArray(['registered_order_id' => $order, 'date_until' => '2026-02-01'])));
    }

    public function test_trashed_profiles_are_never_offenders(): void
    {
        $key = 'bank_profile.requested_amount_zero';
        $profile = $this->offending($key);
        $profile->delete();

        $this->assertNotContains($profile->id, $this->ids($key));
    }

    public function test_purchase_checks_ignore_unsupplied_statuses_and_fire_for_received(): void
    {
        $allocated = $this->checksProfile(['purchased_equivalent' => 0, 'purchased_currency_id' => null, 'purchase_date' => null, 'exchange_rate' => 0], 'Allocated');
        $received = $this->checksProfile(['purchased_equivalent' => 0, 'purchased_currency_id' => null, 'purchase_date' => null, 'exchange_rate' => 0], 'Received');

        foreach (['purchased_without_equivalent', 'purchased_without_currency', 'purchased_without_purchase_date', 'missing_exchange_rate'] as $name) {
            $ids = $this->ids("bank_profile.{$name}");

            $this->assertContains($received->id, $ids);
            $this->assertNotContains($allocated->id, $ids);
        }
    }

    public function test_null_and_zero_are_both_treated_as_missing(): void
    {
        $nullRate = $this->checksProfile(['exchange_rate' => null]);
        $zeroRate = $this->checksProfile(['exchange_rate' => 0]);
        $nullEquivalent = $this->checksProfile(['purchased_equivalent' => null]);

        $this->assertEqualsCanonicalizing([$nullRate->id, $zeroRate->id], $this->ids('bank_profile.missing_exchange_rate'));
        $this->assertSame([$nullEquivalent->id], $this->ids('bank_profile.purchased_without_equivalent'));
    }

    public function test_allocation_date_applies_to_allocated_and_later_statuses_only(): void
    {
        $ready = $this->checksProfile(['allocation_date' => null], 'Ready for Allocation');
        $allocated = $this->checksProfile(['allocation_date' => null], 'Allocated');
        $received = $this->checksProfile(['allocation_date' => null], 'Received');

        $ids = $this->ids('bank_profile.allocated_without_allocation_date');

        $this->assertEqualsCanonicalizing([$allocated->id, $received->id], $ids);
        $this->assertNotContains($ready->id, $ids);
    }

    public function test_stuck_threshold_is_the_class_constant(): void
    {
        $edge = $this->aged($this->checksProfile(), BankProfileChecks::STUCK_DAYS);
        $over = $this->aged($this->checksProfile(), BankProfileChecks::STUCK_DAYS + 1);

        $this->assertSame([$over->id], $this->ids('bank_profile.stuck_in_stage'));
        $this->assertNotContains($edge->id, $this->ids('bank_profile.stuck_in_stage'));
    }

    public function test_stuck_uses_the_latest_status_change_and_ignores_settled_statuses(): void
    {
        $recent = $this->aged($this->checksProfile(), 90);
        StatusHistory::create(['statusable_type' => BankProfile::class, 'statusable_id' => $recent->id, 'field' => 'status_id', 'to_status_id' => $recent->status_id]);
        $settled = $this->aged($this->checksProfile(status: 'Received'), 90);

        $ids = $this->ids('bank_profile.stuck_in_stage');

        $this->assertNotContains($recent->id, $ids);
        $this->assertNotContains($settled->id, $ids);
    }

    public function test_stuck_falls_back_to_updated_at_without_history_and_flags_it(): void
    {
        $profile = $this->unhistoried($this->checksProfile());
        DB::table('bank_profiles')->where('id', $profile->id)->update(['updated_at' => now()->subDays(45)]);
        $check = $this->check('bank_profile.stuck_in_stage');

        $found = ($check->query)(BankProfile::query(), new ReportFilters(registeredOrderId: $this->checksOrder->id))->first();

        $this->assertSame($profile->id, $found->id);
        $this->assertStringContainsString('45', ($check->offending)($found));
        $this->assertStringContainsString(__('resources/report/strings.attention.no_history_note'), ($check->offending)($found));
    }

    public function test_stuck_with_history_is_not_flagged_as_estimated(): void
    {
        $profile = $this->aged($this->checksProfile(), 40);
        $check = $this->check('bank_profile.stuck_in_stage');

        $found = ($check->query)(BankProfile::query(), new ReportFilters(registeredOrderId: $this->checksOrder->id))->first();

        $this->assertSame($profile->id, $found->id);
        $this->assertStringContainsString('40', ($check->offending)($found));
        $this->assertStringNotContainsString(__('resources/report/strings.attention.no_history_note'), ($check->offending)($found));
    }

    public function test_payment_due_windows(): void
    {
        $today = $this->checksProfile(['payment_due_date' => today()->toDateString()], 'Allocated');
        $edge = $this->checksProfile(['payment_due_date' => today()->addDays(BankProfileChecks::DUE_SOON_DAYS)->toDateString()], 'Allocated');
        $beyond = $this->checksProfile(['payment_due_date' => today()->addDays(BankProfileChecks::DUE_SOON_DAYS + 1)->toDateString()], 'Allocated');
        $past = $this->checksProfile(['payment_due_date' => today()->subDay()->toDateString()], 'Allocated');
        $received = $this->checksProfile(['payment_due_date' => today()->subDay()->toDateString()], 'Received');
        $rejected = $this->checksProfile(['payment_due_date' => today()->addDay()->toDateString()], 'Rejected');

        $soon = $this->ids('bank_profile.payment_due_soon');
        $overdue = $this->ids('bank_profile.payment_overdue');

        $this->assertEqualsCanonicalizing([$today->id, $edge->id], $soon);
        $this->assertSame([$past->id], $overdue);
        $this->assertNotContains($beyond->id, array_merge($soon, $overdue));
        $this->assertNotContains($received->id, array_merge($soon, $overdue));
        $this->assertNotContains($rejected->id, array_merge($soon, $overdue));
    }

    public function test_invalid_type_reports_the_foreign_type(): void
    {
        $foreign = $this->checksStatus('Other Kind', 'Foreign Type '.uniqid());
        $profile = $this->checksProfile(['status_id' => $foreign->id]);
        $check = $this->check('bank_profile.status_invalid_type');

        $found = ($check->query)(BankProfile::query(), new ReportFilters(registeredOrderId: $this->checksOrder->id))->first();

        $this->assertSame($profile->id, $found->id);
        $this->assertSame($foreign->english_type, ($check->offending)($found));
        $this->assertStringContainsString(BankProfile::TYPE_BANK_PROFILE, ($check->expected)($found));
    }

    public function test_a_profile_without_status_is_flagged_and_still_listed_by_the_open_payment_checks(): void
    {
        $bare = $this->checksProfile(['status_id' => null, 'payment_due_date' => today()->subDay()->toDateString()]);

        $this->assertContains($bare->id, $this->ids('bank_profile.status_missing'));
        $this->assertContains($bare->id, $this->ids('bank_profile.payment_overdue'));
        $this->assertNotContains($bare->id, $this->ids('bank_profile.status_invalid_type'));
    }

    public function test_no_status_history_only_flags_profiles_without_a_status_id_row(): void
    {
        $bare = $this->unhistoried($this->checksProfile());
        $other = $this->checksProfile();
        StatusHistory::where('statusable_id', $other->id)->where('statusable_type', BankProfile::class)->update(['field' => 'other_field']);

        $ids = $this->ids('bank_profile.no_status_history');

        $this->assertEqualsCanonicalizing([$bare->id, $other->id], $ids);
        $this->assertNotContains($this->checksProfile()->id, $this->ids('bank_profile.no_status_history'));
    }

    public function test_count_queries_do_not_hydrate_rows(): void
    {
        $this->offending('bank_profile.stuck_in_stage');
        $this->offending('bank_profile.stuck_in_stage');
        $check = $this->check('bank_profile.stuck_in_stage');

        $count = ($check->query)(BankProfile::query(), new ReportFilters(registeredOrderId: $this->checksOrder->id))->count();

        $this->assertSame(2, $count);
    }

    private function checksFilters(array $extra = []): ReportFilters
    {
        return ReportFilters::fromArray(['registered_order_id' => $this->checksOrder->id] + $extra);
    }

    private function idsWith(string $key, array $extra): array
    {
        return $this->ids($key, $this->checksFilters($extra));
    }

    public function test_the_stuck_threshold_filter_moves_the_check_the_expected_text_and_the_default_stays_30(): void
    {
        $profile = $this->aged($this->checksProfile(), 20);
        $check = $this->check('bank_profile.stuck_in_stage');

        $this->assertNotContains($profile->id, $this->idsWith('bank_profile.stuck_in_stage', []));
        $this->assertContains($profile->id, $this->idsWith('bank_profile.stuck_in_stage', ['stuck_days' => 10]));
        $this->assertNotContains($profile->id, $this->idsWith('bank_profile.stuck_in_stage', ['stuck_days' => 60]));

        $row = app(CheckRegistry::class)->evaluate($check, $this->checksFilters(['stuck_days' => 10]))->firstWhere('id', $profile->id);
        $this->assertStringContainsString('10', $row['expected']);
        $this->assertSame(30, $this->checksFilters()->threshold('stuck'));
    }

    public function test_the_due_soon_threshold_filter_moves_the_check_and_its_text(): void
    {
        $profile = $this->checksProfile(['payment_due_date' => today()->addDays(10)->toDateString()], 'Allocated');
        $check = $this->check('bank_profile.payment_due_soon');

        $this->assertContains($profile->id, $this->idsWith('bank_profile.payment_due_soon', []));
        $this->assertNotContains($profile->id, $this->idsWith('bank_profile.payment_due_soon', ['th_due_soon' => 5]));
        $this->assertContains($profile->id, $this->idsWith('bank_profile.payment_due_soon', ['th_due_soon' => 10]));
        $this->assertStringContainsString('5', ($check->expected)($profile, $this->checksFilters(['th_due_soon' => 5])));
        $this->assertSame(14, $this->checksFilters()->threshold('due_soon'));
    }

    public function test_the_date_basis_decides_which_date_the_range_is_compared_with(): void
    {
        $a = $this->checksProfile(['requested_amount' => 0, 'creation_date' => '2026-01-10', 'allocation_date' => '2026-03-10', 'purchase_date' => '2026-05-10', 'payment_due_date' => '2026-07-10']);
        $b = $this->checksProfile(['requested_amount' => 0, 'creation_date' => '2026-03-10', 'allocation_date' => '2026-01-10', 'purchase_date' => '2026-07-10', 'payment_due_date' => '2026-05-10']);
        $key = 'bank_profile.requested_amount_zero';

        $this->assertSame([$b->id], $this->idsWith($key, ['date_from' => '2026-02-01']));
        $this->assertSame([$a->id], $this->idsWith($key, ['date_from' => '2026-02-01', 'date_basis' => 'allocation_date']));
        $this->assertSame([$b->id], $this->idsWith($key, ['date_from' => '2026-06-01', 'date_basis' => 'purchase_date']));
        $this->assertSame([$a->id], $this->idsWith($key, ['date_from' => '2026-06-01', 'date_basis' => 'payment_due_date']));
        $this->assertSame([$b->id], $this->idsWith($key, ['date_from' => '2026-02-01', 'date_basis' => 'unknown_basis']));
    }

    public function test_undated_profiles_stay_visible_in_every_surface_unless_switched_off(): void
    {
        $dated = $this->builderProfile('Allocated', 100, ['creation_date' => '2026-01-10', 'allocation_date' => '2026-01-12']);
        $undated = $this->builderProfile('Allocated', 100, ['creation_date' => '2026-01-10', 'allocation_date' => null]);
        $base = ['registered_order_id' => $this->builderOrder->id, 'date_from' => '2026-02-01', 'date_basis' => 'allocation_date'];
        $included = ReportFilters::fromArray($base);
        $excluded = ReportFilters::fromArray($base + ['include_undated' => false]);
        $count = fn (ReportFilters $filters): int => $this->builderService()->query($filters)->count();
        $line = fn (ReportFilters $filters): string => collect($this->builderService()->summary($filters))->firstWhere('key', 'allocated')['lines'][0]['value'];

        $this->assertSame([$undated->id], $this->builderService()->query($included)->pluck('bank_profiles.id')->all());
        $this->assertSame(1, $count($included));
        $this->assertSame('1', $line($included));
        $this->assertSame(0, $count($excluded));
        $this->assertSame('0', $line($excluded));
        $this->assertNotContains($dated->id, $this->builderService()->query($included)->pluck('bank_profiles.id')->all());
    }

    public function test_the_purchased_card_follows_the_selected_period_and_equals_its_rows(): void
    {
        $base = ['creation_date' => '2026-02-05', 'purchased_currency_id' => $this->usd->id];
        $inRange = $this->builderProfile('Purchased', 100, $base + ['purchased_equivalent' => 50, 'purchase_date' => '2026-02-10']);
        $outRange = $this->builderProfile('Purchased', 100, $base + ['purchased_equivalent' => 70, 'purchase_date' => '2026-05-10']);
        $undated = $this->builderProfile('Purchased', 100, $base + ['purchased_equivalent' => 20, 'purchase_date' => null]);
        $range = ['registered_order_id' => $this->builderOrder->id, 'date_from' => '2026-02-01', 'date_until' => '2026-02-28'];

        foreach ([[], ['date_basis' => 'purchase_date'], ['date_basis' => 'creation_date']] as $extra) {
            $filters = ReportFilters::fromArray($range + $extra + ['quick' => 'purchased_month']);
            $card = collect($this->builderService()->summary($filters))->firstWhere('key', 'purchased_month');
            $rows = $this->builderService()->query($filters)->pluck('bank_profiles.id')->all();

            $this->assertSame(__('resources/report/strings.fx_supply.cards.purchased_period'), $card['label']);
            $this->assertEqualsCanonicalizing([$inRange->id], $rows, json_encode($extra));
            $this->assertNotContains($undated->id, $rows, json_encode($extra));
            $this->assertSame((string) count($rows), $card['lines'][0]['value'], json_encode($extra));
        }

        $strict = ReportFilters::fromArray($range + ['include_undated' => false, 'quick' => 'purchased_month']);
        $this->assertSame([$inRange->id], $this->builderService()->query($strict)->pluck('bank_profiles.id')->all());
        $this->assertNotContains($outRange->id, $this->builderService()->query($strict)->pluck('bank_profiles.id')->all());
        $this->assertSame(delimiter(50), collect(collect($this->builderService()->summary($strict))->firstWhere('key', 'purchased_month')['lines'])->pluck('value', 'label')[$this->usd->getLocalizedNameAttribute()]);
    }

    public function test_the_purchased_card_counts_a_profile_created_earlier_whatever_the_date_basis(): void
    {
        $profile = $this->builderProfile('Purchased', 100, ['creation_date' => '2026-06-05', 'purchased_currency_id' => $this->usd->id, 'purchased_equivalent' => 50, 'purchase_date' => '2026-09-15']);
        $range = ['registered_order_id' => $this->builderOrder->id, 'date_from' => '2026-09-01', 'date_until' => '2026-09-30', 'include_undated' => false];

        foreach ([[], ['date_basis' => 'creation_date']] as $extra) {
            $filters = ReportFilters::fromArray($range + $extra + ['quick' => 'purchased_month']);
            $card = collect($this->builderService()->summary($filters))->firstWhere('key', 'purchased_month');

            $this->assertSame('1', $card['lines'][0]['value'], json_encode($extra));
            $this->assertSame([$profile->id], $this->builderService()->query($filters)->pluck('bank_profiles.id')->all(), json_encode($extra));
            $this->assertSame([$profile->id], $this->builderService()->query(ReportFilters::fromArray($range + $extra + ['quick' => 'purchased_month_currency_'.$this->usd->id]))->pluck('bank_profiles.id')->all());
        }

        $others = ReportFilters::fromArray($range + ['quick' => 'queued']);
        $this->assertSame([], $this->builderService()->query($others)->pluck('bank_profiles.id')->all());
    }

    public function test_without_a_range_the_purchased_card_keeps_the_current_month_and_its_old_label(): void
    {
        $this->builderProfile('Purchased', 100, ['purchased_currency_id' => $this->usd->id, 'purchased_equivalent' => 5, 'purchase_date' => today()->toDateString()]);
        $this->builderProfile('Purchased', 100, ['purchased_currency_id' => $this->usd->id, 'purchased_equivalent' => 5, 'purchase_date' => today()->subMonths(2)->toDateString()]);
        $card = $this->card('purchased_month');

        $this->assertSame(__('resources/report/strings.fx_supply.cards.purchased_month'), $card['label']);
        $this->assertSame('1', $card['lines'][0]['value']);
        $this->assertCount(1, $this->builderService()->query($this->quickFilters('purchased_month'))->get());
    }

    public function test_the_requested_and_purchased_currency_filters_apply_to_every_surface_consistently(): void
    {
        $eur = Currency::factory()->create();
        $month = today()->toDateString();
        $a = $this->builderProfile('Purchased', 100, ['requested_currency_id' => $this->usd->id, 'purchased_currency_id' => $eur->id, 'purchased_equivalent' => 90, 'purchase_date' => $month]);
        $b = $this->builderProfile('Purchased', 100, ['requested_currency_id' => $eur->id, 'purchased_currency_id' => $this->usd->id, 'purchased_equivalent' => 80, 'purchase_date' => $month]);
        $ids = fn (array $extra): array => $this->builderService()->query(ReportFilters::fromArray(['registered_order_id' => $this->builderOrder->id, 'show_closed' => true] + $extra))->pluck('bank_profiles.id')->all();
        $line = fn (array $extra): string => collect($this->builderService()->summary(ReportFilters::fromArray(['registered_order_id' => $this->builderOrder->id] + $extra)))->firstWhere('key', 'purchased_month')['lines'][0]['value'];

        $this->assertSame([$a->id], $ids(['currency_id' => $this->usd->id]));
        $this->assertSame([$b->id], $ids(['purchased_currency_id' => $this->usd->id]));
        $this->assertSame([], $ids(['currency_id' => $this->usd->id, 'purchased_currency_id' => $this->usd->id]));
        $this->assertSame('1', $line(['currency_id' => $this->usd->id]));
        $this->assertSame('1', $line(['purchased_currency_id' => $this->usd->id]));
        $this->assertSame('0', $line(['currency_id' => $this->usd->id, 'purchased_currency_id' => $this->usd->id]));
        $this->assertSame(
            $this->builderService()->chartFunnel(ReportFilters::fromArray(['registered_order_id' => $this->builderOrder->id, 'purchased_currency_id' => $this->usd->id])),
            $this->builderService()->chartFunnel(ReportFilters::fromArray(['registered_order_id' => $this->builderOrder->id, 'purchased_currency_id' => $this->usd->id, 'quick' => 'purchased_month'])),
        );
    }

    public function test_the_export_carries_the_targetable_and_its_labels_match_the_table_label(): void
    {
        $this->assertSame(__('resources/bankProfile/strings.table.targetable'), $this->builderService()->exportLabels()['targetable']);
        $profile = $this->builderProfile('Allocated');

        $row = collect($this->builderService()->rows($this->builderFilters()))->firstWhere('bp_number', $profile->bp_number);

        $this->assertArrayHasKey('targetable', $row);
    }

    public function test_the_date_order_check_names_the_offending_pair_and_ignores_missing_dates(): void
    {
        $both = $this->checksProfile(['creation_date' => '2026-01-10', 'allocation_date' => '2026-01-05', 'purchase_date' => '2026-01-01']);
        $purchaseOnly = $this->checksProfile(['creation_date' => '2026-01-01', 'allocation_date' => '2026-01-10', 'purchase_date' => '2026-01-05']);
        $partial = $this->checksProfile(['creation_date' => '2026-01-10', 'allocation_date' => null, 'purchase_date' => '2026-01-01']);
        $check = $this->check('bank_profile.date_order_invalid');

        $ids = $this->ids('bank_profile.date_order_invalid');
        $found = ($check->query)(BankProfile::query(), $this->checksFilters())->get()->keyBy('id');

        $this->assertEqualsCanonicalizing([$both->id, $purchaseOnly->id], $ids);
        $this->assertNotContains($partial->id, $ids);
        $this->assertSame(2, substr_count(($check->offending)($found[$both->id]), '<'));
        $this->assertSame(1, substr_count(($check->offending)($found[$purchaseOnly->id]), '<'));
    }

    public function test_missing_due_date_counts_open_profiles_without_status_and_skips_settled_ones(): void
    {
        $open = $this->checksProfile(['payment_due_date' => null], 'Allocated');
        $noStatus = $this->checksProfile(['payment_due_date' => null, 'status_id' => null]);
        $received = $this->checksProfile(['payment_due_date' => null], 'Received');
        $rejected = $this->checksProfile(['payment_due_date' => null], 'Rejected');
        $dated = $this->checksProfile(['payment_due_date' => today()->addDays(90)->toDateString()], 'Allocated');

        $ids = $this->ids('bank_profile.missing_payment_due_date');

        $this->assertEqualsCanonicalizing([$open->id, $noStatus->id], $ids);
        $this->assertNotContains($received->id, $ids);
        $this->assertNotContains($rejected->id, $ids);
        $this->assertNotContains($dated->id, $ids);
    }

    public function test_supplied_without_bank_covers_allocated_and_later_only(): void
    {
        $allocated = $this->checksProfile(['bank_id' => null], 'Allocated');
        $received = $this->checksProfile(['bank_id' => null], 'Received');
        $ready = $this->checksProfile(['bank_id' => null], 'Ready for Allocation');
        $withBank = $this->checksProfile([], 'Allocated');

        $ids = $this->ids('bank_profile.supplied_without_bank');

        $this->assertEqualsCanonicalizing([$allocated->id, $received->id], $ids);
        $this->assertNotContains($ready->id, $ids);
        $this->assertNotContains($withBank->id, $ids);
    }

    private function builderLines(array $extra = []): \Illuminate\Support\Collection
    {
        return collect($this->builderService()->summary(ReportFilters::fromArray(['registered_order_id' => $this->builderOrder->id] + $extra)))->flatMap(fn (array $card): array => $card['lines']);
    }

    private function builderRowIds(array $extra = []): array
    {
        return $this->builderService()->query(ReportFilters::fromArray(['registered_order_id' => $this->builderOrder->id] + $extra))->pluck('bank_profiles.id')->all();
    }

    public function test_finished_profiles_are_hidden_by_default_shown_with_the_toggle_and_counted_in_the_note(): void
    {
        $open = $this->builderProfile('Allocated');
        $purchased = $this->builderProfile('Purchased', 100, ['purchased_currency_id' => $this->usd->id, 'purchased_equivalent' => 90]);
        $received = $this->builderProfile('Received');
        $rejected = $this->builderProfile('Rejected');
        $note = __('resources/report/strings.fx_supply.lines.finished_hidden');

        $this->assertSame([$open->id], $this->builderRowIds());
        $this->assertEqualsCanonicalizing([$open->id, $purchased->id, $received->id, $rejected->id], $this->builderRowIds(['show_closed' => true]));
        $this->assertSame('3', $this->builderLines()->firstWhere('label', $note)['value']);
        $this->assertNull($this->builderLines(['show_closed' => true])->firstWhere('label', $note));
        $this->assertArrayNotHasKey('quick', $this->builderLines()->firstWhere('label', $note));
        $this->assertSame(3, array_sum($this->builderService()->chartFunnel($this->builderFilters())['counts']));
        $this->assertSame([$open->id], $this->builderRowIds(['quick' => 'nonsense']));
    }

    public function test_the_finished_rule_does_not_hide_profiles_without_a_status(): void
    {
        $none = $this->builderProfile(null);

        $this->assertSame([$none->id], $this->builderRowIds());
    }

    public function test_quick_lines_that_include_finished_profiles_widen_their_own_view_and_equal_their_rows(): void
    {
        $this->quickFixtures();
        $due = today()->addDays(5)->toDateString();
        $this->builderProfile('Purchased', 100, ['purchased_currency_id' => $this->usd->id, 'purchased_equivalent' => 10, 'purchase_date' => today()->toDateString(), 'payment_due_date' => $due]);
        $this->builderProfile('Allocated', 100, ['payment_due_date' => today()->subDays(3)->toDateString()]);

        foreach ([[], ['show_closed' => true], ['th_due_soon' => 3], ['th_due_soon' => 30, 'show_closed' => true]] as $extra) {
            foreach ($this->builderLines($extra)->filter(fn (array $line): bool => isset($line['quick']) && ! str_contains($line['quick'], '_currency_')) as $line) {
                $this->assertSame((int) $line['value'], count($this->builderRowIds($extra + ['quick' => $line['quick']])), $line['quick'].' '.json_encode($extra));
            }
        }

        $purchasedRows = $this->builderService()->query($this->quickFilters('purchased_month'))->get();

        $this->assertTrue($purchasedRows->isNotEmpty());
        $this->assertSame([], $purchasedRows->reject(fn (BankProfile $profile): bool => in_array($profile->status?->english_name, ['Purchased', 'Received'], true))->all());
    }

    public function test_the_payment_lines_use_the_check_predicates_and_the_due_soon_threshold(): void
    {
        $soon = $this->builderProfile('Allocated', 100, ['payment_due_date' => today()->addDays(20)->toDateString()]);
        $purchasedLate = $this->builderProfile('Purchased', 100, ['payment_due_date' => today()->subDay()->toDateString()]);
        $settled = $this->builderProfile('Received', 100, ['payment_due_date' => today()->subDays(5)->toDateString()]);
        $rejected = $this->builderProfile('Rejected', 100, ['payment_due_date' => today()->addDays(2)->toDateString()]);
        $line = fn (string $quick, array $extra = []): array => $this->builderLines($extra)->firstWhere('quick', $quick);
        $check = fn (string $key, array $extra = []): array => ($this->check($key)->query)(BankProfile::query(), ReportFilters::fromArray(['registered_order_id' => $this->builderOrder->id] + $extra))->pluck('bank_profiles.id')->all();

        $this->assertSame('0', $line('payment_due_soon')['value']);
        $this->assertSame('1', $line('payment_due_soon', ['th_due_soon' => 25])['value']);
        $this->assertSame(__('resources/report/strings.fx_supply.lines.payment_due_soon', ['days' => 14]), $line('payment_due_soon')['label']);
        $this->assertSame(__('resources/report/strings.fx_supply.lines.payment_due_soon', ['days' => 25]), $line('payment_due_soon', ['th_due_soon' => 25])['label']);
        $this->assertSame([$soon->id], $this->builderRowIds(['quick' => 'payment_due_soon', 'th_due_soon' => 25]));
        $this->assertSame([$soon->id], $check('bank_profile.payment_due_soon', ['th_due_soon' => 25]));
        $this->assertSame([], $check('bank_profile.payment_due_soon'));
        $this->assertSame('1', $line('payment_overdue')['value']);
        $this->assertSame([$purchasedLate->id], $this->builderRowIds(['quick' => 'payment_overdue']));
        $this->assertSame([$purchasedLate->id], $check('bank_profile.payment_overdue'));
        $this->assertNotContains($settled->id, $check('bank_profile.payment_overdue'));
        $this->assertNotContains($rejected->id, $check('bank_profile.payment_due_soon', ['th_due_soon' => 25]));
    }

    public function test_each_amount_card_says_which_currency_it_is_in_in_every_locale(): void
    {
        $this->builderProfile('Allocated');

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $cards = collect($this->builderService()->summary($this->builderFilters()))->keyBy('key');
            $labels = fn (string $key): array => collect($cards[$key]['lines'])->pluck('label')->all();

            $this->assertContains(__('resources/report/strings.fx_supply.lines.requested_currency_note'), $labels('queued'), $locale);
            $this->assertContains(__('resources/report/strings.fx_supply.lines.requested_currency_note'), $labels('allocated'), $locale);
            $this->assertContains(__('resources/report/strings.fx_supply.lines.purchased_currency_note'), $labels('purchased_month'), $locale);
            $this->assertNotSame(__('resources/report/strings.fx_supply.lines.requested_currency_note'), __('resources/report/strings.fx_supply.lines.purchased_currency_note'), $locale);
            $this->assertStringNotContainsString('strings.', __('resources/report/strings.fx_supply.lines.requested_currency_note'), $locale);
        }

        $this->assertSame(['queued', 'allocated', 'purchased_month', 'timing_payments'], array_column($this->builderService()->summary($this->builderFilters()), 'key'));
    }

    public function test_card_rows_and_check_agree_for_the_missing_due_date_population(): void
    {
        $this->checksProfile(['payment_due_date' => null], 'Allocated');
        $this->checksProfile(['payment_due_date' => null], 'Allocated');
        $filters = $this->checksFilters();
        $allocated = collect($this->builderService()->summary($filters))->firstWhere('key', 'allocated')['lines'][0]['value'];
        $rows = $this->builderService()->query($this->checksFilters(['quick' => 'allocated']))->count();

        $this->assertSame((string) $rows, $allocated);
        $this->assertCount($rows, $this->ids('bank_profile.missing_payment_due_date'));
    }
}
