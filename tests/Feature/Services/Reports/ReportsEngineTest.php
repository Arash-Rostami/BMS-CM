<?php

namespace Tests\Feature\Services\Reports;

use App\Filament\Resources\Operational\ShipmentResource\Enums\TrackingStatus;
use App\Models\BankProfile;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Custom;
use App\Models\EntityAttribute;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\Calendar\CalendarModules;
use App\Services\Reports\Checks\Check;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Checks\CustomChecks;
use App\Services\Reports\Rules\FxRateService;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Rules\ShipmentCurrencyResolver;
use App\Services\Reports\Support\ReportCsvWriter;
use App\Services\Reports\Support\ReportFilters;
use App\Services\SmartCacheManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReportsEngineTest extends TestCase
{
    private const ZERO = 'bank_profile.requested_amount_zero';

    private const STUCK = 'bank_profile.stuck_in_stage';

    private array $checksStatuses = [];

    private ?RegisteredOrder $checksOrder = null;

    private string $csvPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        $this->resetCaches();
        $this->csvPath = tempnam(sys_get_temp_dir(), 'report_csv_');
    }

    protected function tearDown(): void
    {
        CheckRegistry::invalidate();
        DB::rollBack();
        $this->resetCaches();
        @unlink($this->csvPath);
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

    private function resetCaches(): void
    {
        FxRateService::flush();
        ReportStages::flush();
        SmartCacheManager::invalidate('Status');
        CheckRegistry::invalidate();
    }

    private function fxService(): FxRateService
    {
        return app(FxRateService::class);
    }

    private function fxStatus(string $name): Status
    {
        return Status::factory()->create([
            'type' => BankProfile::TYPE_BANK_PROFILE,
            'english_type' => BankProfile::TYPE_BANK_PROFILE,
            'name' => $name,
            'english_name' => $name,
        ]);
    }

    private function fxProfile(Currency $requested, Currency $purchased, float $amount, float $equivalent, ?Status $status = null, ?string $date = '2026-01-01'): BankProfile
    {
        return BankProfile::factory()->create([
            'status_id' => ($status ?? $this->fxStatus('Purchased'))->id,
            'requested_currency_id' => $requested->id,
            'purchased_currency_id' => $purchased->id,
            'requested_amount' => $amount,
            'purchased_equivalent' => $equivalent,
            'purchase_date' => $date,
        ]);
    }

    private function resolver(): ShipmentCurrencyResolver
    {
        return app(ShipmentCurrencyResolver::class);
    }

    private function resolverShipment(?Currency $order = null, ?Currency $proforma = null, ?string $invoiceCurrency = null): Shipment
    {
        $registered = RegisteredOrder::factory()->create(['currency_id' => $order?->id ?? $this->resolverCurrencies(1)[0]->id]);

        if ($proforma) {
            $registered->proformaInvoices()->attach(ProformaInvoice::factory()->create(['main_currency_id' => $proforma->id])->id);
        }

        $shipment = Shipment::factory()->create(['registered_order_id' => $registered->id]);

        if ($invoiceCurrency !== null) {
            EntityAttribute::factory()->forEntity($shipment)->create([
                'key' => 'commercial_invoice',
                'value' => ['currency' => $invoiceCurrency],
            ]);
        }

        $shipment = $shipment->fresh()->load('registeredOrder.proformaInvoices');

        if (! $order) {
            $shipment->registeredOrder->currency_id = null;
        }

        return $shipment;
    }

    private function resolverCurrencies(int $count): array
    {
        return array_map(fn () => Currency::factory()->create([
            'english_name' => 'T'.uniqid(),
            'name' => 'N'.uniqid(),
        ]), range(1, $count));
    }

    private function stages(): ReportStages
    {
        return app(ReportStages::class);
    }

    private function stagesStatus(string $type, string $name, ?int $order = null): Status
    {
        return Status::factory()->create([
            'type' => $type,
            'english_type' => $type,
            'name' => $name,
            'english_name' => $name,
            'stage_order' => $order,
        ]);
    }

    private function clearType(string $type): void
    {
        Status::where('english_type', $type)->delete();
        $this->resetCaches();
    }

    private function csvLines(): array
    {
        return array_values(array_filter(explode("\n", str_replace("\r\n", "\n", substr((string) file_get_contents($this->csvPath), 3)))));
    }

    private function checksOrder(): RegisteredOrder
    {
        return $this->checksOrder ??= RegisteredOrder::factory()->create();
    }

    private function registry(): CheckRegistry
    {
        return app(CheckRegistry::class);
    }

    private function check(string $key): Check
    {
        return $this->registry()->all()->firstWhere('key', $key);
    }

    private function filters(?RegisteredOrder $order = null): ReportFilters
    {
        return new ReportFilters(registeredOrderId: ($order ?? $this->checksOrder())->id);
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'check_role_'.uniqid(), 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function checksStatus(string $name): Status
    {
        return $this->checksStatuses[$name] ??= Status::factory()->create([
            'type' => BankProfile::TYPE_BANK_PROFILE,
            'english_type' => BankProfile::TYPE_BANK_PROFILE,
            'name' => $name,
            'english_name' => $name,
        ]);
    }

    private function checksProfile(array $attributes = [], ?RegisteredOrder $order = null): BankProfile
    {
        return BankProfile::factory()->create($attributes + [
            'registered_order_id' => ($order ?? $this->checksOrder())->id,
            'status_id' => $this->checksStatus('Purchased')->id,
            'requested_amount' => 1000,
            'purchased_equivalent' => 900,
            'exchange_rate' => 5,
            'creation_date' => '2026-01-01',
            'allocation_date' => '2026-01-02',
            'purchase_date' => '2026-01-03',
            'payment_due_date' => null,
        ]);
    }

    private function zero(?RegisteredOrder $order = null): BankProfile
    {
        return $this->checksProfile(['requested_amount' => 0], $order);
    }

    private function stuck(?RegisteredOrder $order = null, int $days = 40): BankProfile
    {
        $profile = $this->checksProfile(order: $order);
        StatusHistory::where('statusable_type', BankProfile::class)->where('statusable_id', $profile->id)->update(['created_at' => now()->subDays($days)]);

        return $profile;
    }

    private function queries(callable $callback): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_direction_a_divides_purchased_equivalent_by_requested_amount(): void
    {
        [$usd, $eur] = Currency::factory()->count(2)->create();
        $profile = $this->fxProfile($usd, $eur, 1000, 900);

        $rate = $this->fxService()->rate($usd->id, $eur->id);

        $this->assertEqualsWithDelta(0.9, $rate['rate'], 0.00001);
        $this->assertSame($profile->id, $rate['bank_profile_id']);
        $this->assertSame('2026-01-01', $rate['date']->toDateString());
    }

    public function test_direction_b_divides_requested_amount_by_purchased_equivalent(): void
    {
        [$usd, $eur] = Currency::factory()->count(2)->create();
        $this->fxProfile($eur, $usd, 900, 1000);

        $rate = $this->fxService()->rate($usd->id, $eur->id);

        $this->assertEqualsWithDelta(0.9, $rate['rate'], 0.00001);
    }

    public function test_latest_purchase_date_wins_across_both_directions(): void
    {
        [$usd, $eur] = Currency::factory()->count(2)->create();
        $this->fxProfile($usd, $eur, 1000, 900, null, '2026-01-01');
        $newest = $this->fxProfile($eur, $usd, 800, 1000, null, '2026-03-01');
        $this->fxProfile($usd, $eur, 1000, 950, null, '2026-02-01');

        $rate = $this->fxService()->rate($usd->id, $eur->id);

        $this->assertSame($newest->id, $rate['bank_profile_id']);
        $this->assertEqualsWithDelta(0.8, $rate['rate'], 0.00001);
    }

    public function test_dated_profile_beats_undated_and_highest_id_breaks_date_ties(): void
    {
        [$usd, $eur] = Currency::factory()->count(2)->create();
        $this->fxProfile($usd, $eur, 1000, 900, null, null);
        $dated = $this->fxProfile($usd, $eur, 1000, 910, null, '2026-01-01');
        $tied = $this->fxProfile($usd, $eur, 1000, 920, null, '2026-01-01');

        $this->assertNotSame($dated->id, $tied->id);
        $this->assertSame($tied->id, $this->fxService()->rate($usd->id, $eur->id)['bank_profile_id']);
    }

    public function test_received_status_is_a_valid_source(): void
    {
        [$usd, $eur] = Currency::factory()->count(2)->create();
        $profile = $this->fxProfile($usd, $eur, 1000, 900, $this->fxStatus('Received'));

        $this->assertSame($profile->id, $this->fxService()->rate($usd->id, $eur->id)['bank_profile_id']);
    }

    public function test_returns_null_without_a_candidate(): void
    {
        [$usd, $eur] = Currency::factory()->count(2)->create();

        $this->assertNull($this->fxService()->rate($usd->id, $eur->id));
    }

    public function test_zero_amounts_are_ignored(): void
    {
        [$usd, $eur] = Currency::factory()->count(2)->create();
        $this->fxProfile($usd, $eur, 0, 900);
        $this->fxProfile($usd, $eur, 1000, 0);

        $this->assertNull($this->fxService()->rate($usd->id, $eur->id));
    }

    public function test_wrong_status_is_ignored(): void
    {
        [$usd, $eur] = Currency::factory()->count(2)->create();
        $this->fxProfile($usd, $eur, 1000, 900, $this->fxStatus('Allocated'));

        $this->assertNull($this->fxService()->rate($usd->id, $eur->id));
    }

    public function test_trashed_profile_is_ignored(): void
    {
        [$usd, $eur] = Currency::factory()->count(2)->create();
        $this->fxProfile($usd, $eur, 1000, 900)->delete();

        $this->assertNull($this->fxService()->rate($usd->id, $eur->id));
    }

    public function test_same_currency_is_one(): void
    {
        $this->assertSame(['rate' => 1.0, 'bank_profile_id' => 0, 'date' => null], $this->fxService()->rate(7, 7));
    }

    public function test_flush_invalidates_cached_rates(): void
    {
        [$usd, $eur] = Currency::factory()->count(2)->create();
        $profile = $this->fxProfile($usd, $eur, 1000, 900);

        $this->assertEqualsWithDelta(0.9, $this->fxService()->rate($usd->id, $eur->id)['rate'], 0.00001);

        $profile->update(['purchased_equivalent' => 800]);

        $this->assertEqualsWithDelta(0.8, $this->fxService()->rate($usd->id, $eur->id)['rate'], 0.00001);

        $profile->updateQuietly(['purchased_equivalent' => 700]);
        FxRateService::flush();

        $this->assertEqualsWithDelta(0.7, $this->fxService()->rate($usd->id, $eur->id)['rate'], 0.00001);
    }

    public function test_eur_currency_id_matches_name_case_insensitively_among_active_rows(): void
    {
        Currency::whereRaw('LOWER(english_name) = ?', ['euro'])->delete();
        Currency::factory()->inactive()->create(['english_name' => 'Euro']);
        $this->assertNull($this->fxService()->eurCurrencyId());

        FxRateService::flush();
        $eur = Currency::factory()->create(['english_name' => 'EURO']);

        $this->assertSame($eur->id, $this->fxService()->eurCurrencyId());
    }

    public function test_eur_currency_id_ignores_trashed_rows(): void
    {
        Currency::whereRaw('LOWER(english_name) = ?', ['euro'])->delete();
        Currency::factory()->create(['english_name' => 'Euro'])->delete();

        $this->assertNull($this->fxService()->eurCurrencyId());
    }

    public function test_to_eur_converts_and_passes_eur_through(): void
    {
        Currency::whereRaw('LOWER(english_name) = ?', ['euro'])->delete();
        $eur = Currency::factory()->create(['english_name' => 'Euro']);
        $usd = Currency::factory()->create();
        $this->fxProfile($usd, $eur, 1000, 900);

        $this->assertEqualsWithDelta(450.0, $this->fxService()->toEur('500', $usd->id), 0.00001);
        $this->assertEqualsWithDelta(450.0, $this->fxService()->toEur(500.0, $usd->id), 0.00001);
        $this->assertSame(123.5, $this->fxService()->toEur(123.5, $eur->id));
    }

    public function test_to_eur_returns_null_for_unusable_input(): void
    {
        Currency::whereRaw('LOWER(english_name) = ?', ['euro'])->delete();
        $eur = Currency::factory()->create(['english_name' => 'Euro']);
        $noRate = Currency::factory()->create();

        $this->assertNull($this->fxService()->toEur(null, $eur->id));
        $this->assertNull($this->fxService()->toEur('abc', $eur->id));
        $this->assertNull($this->fxService()->toEur(10, null));
        $this->assertNull($this->fxService()->toEur(10, $noRate->id));
    }

    public function test_to_eur_returns_null_when_eur_currency_is_missing(): void
    {
        Currency::whereRaw('LOWER(english_name) = ?', ['euro'])->delete();
        $usd = Currency::factory()->create();

        $this->assertNull($this->fxService()->toEur(10, $usd->id));
    }

    public function test_invoice_currency_wins_and_matches_english_name_case_insensitively(): void
    {
        [$invoice, $order, $proforma] = $this->resolverCurrencies(3);
        $shipment = $this->resolverShipment($order, $proforma, '  '.strtolower($invoice->english_name).' ');

        $this->assertSame(
            ['currency_id' => $invoice->id, 'source' => 'invoice', 'mismatch' => true],
            $this->resolver()->resolve($shipment),
        );
    }

    public function test_invoice_currency_matches_localized_name(): void
    {
        [$invoice, $order] = $this->resolverCurrencies(2);
        $shipment = $this->resolverShipment($order, null, $invoice->name);

        $this->assertSame($invoice->id, $this->resolver()->resolve($shipment)['currency_id']);
    }

    public function test_a_double_encoded_or_broken_invoice_header_never_throws_and_degrades_to_the_order(): void
    {
        [$invoice, $order] = $this->resolverCurrencies(2);
        $double = $this->resolverShipment($order);
        $broken = $this->resolverShipment($order);
        $scalar = $this->resolverShipment($order);
        $empty = $this->resolverShipment($order);
        $bare = $this->resolverShipment($order);
        $values = [
            [$double, json_encode(['currency' => $invoice->english_name])],
            [$broken, 'not json at all {'],
            [$scalar, '123'],
            [$empty, '"just a string"'],
        ];

        foreach ($values as [$shipment, $value]) {
            EntityAttribute::factory()->forEntity($shipment)->create(['key' => 'commercial_invoice', 'value' => $value]);
        }

        $resolved = $this->resolver()->resolveMany(collect([$double, $broken, $scalar, $empty, $bare]));

        $this->assertSame(['currency_id' => $invoice->id, 'source' => 'invoice', 'mismatch' => true], $resolved[$double->id]);

        foreach ([$broken, $scalar, $empty, $bare] as $shipment) {
            $this->assertSame(['currency_id' => $order->id, 'source' => 'order', 'mismatch' => false], $resolved[$shipment->id]);
        }
    }

    public function test_no_mismatch_when_invoice_equals_order(): void
    {
        $currency = $this->resolverCurrencies(1)[0];
        $shipment = $this->resolverShipment($currency, null, $currency->english_name);

        $this->assertSame(
            ['currency_id' => $currency->id, 'source' => 'invoice', 'mismatch' => false],
            $this->resolver()->resolve($shipment),
        );
    }

    public function test_unmatched_invoice_text_falls_back_to_order(): void
    {
        $order = $this->resolverCurrencies(1)[0];
        $shipment = $this->resolverShipment($order, null, 'NOPE-'.uniqid());

        $this->assertSame(
            ['currency_id' => $order->id, 'source' => 'order', 'mismatch' => false],
            $this->resolver()->resolve($shipment),
        );
    }

    public function test_trashed_currency_is_not_matched_by_the_invoice(): void
    {
        $order = $this->resolverCurrencies(1)[0];
        $trashed = $this->resolverCurrencies(1)[0];
        $trashed->delete();
        $shipment = $this->resolverShipment($order, null, $trashed->english_name);

        $this->assertSame('order', $this->resolver()->resolve($shipment)['source']);
    }

    public function test_proforma_is_used_when_order_has_no_currency(): void
    {
        $proforma = $this->resolverCurrencies(1)[0];
        $shipment = $this->resolverShipment(null, $proforma);

        $this->assertSame(
            ['currency_id' => $proforma->id, 'source' => 'proforma', 'mismatch' => false],
            $this->resolver()->resolve($shipment),
        );
    }

    public function test_everything_missing_resolves_to_null(): void
    {
        $this->assertSame(
            ['currency_id' => null, 'source' => null, 'mismatch' => false],
            $this->resolver()->resolve($this->resolverShipment()),
        );
    }

    public function test_resolve_many_costs_the_same_queries_for_one_shipment_or_many(): void
    {
        [$a, $b] = $this->resolverCurrencies(2);
        $one = collect([$this->resolverShipment($a, null, $b->english_name)]);
        $many = collect([
            $this->resolverShipment($a, null, $b->english_name),
            $this->resolverShipment($a, null, $a->english_name),
            $this->resolverShipment($b, null, $a->english_name),
            $this->resolverShipment($a),
        ]);

        $count = function ($shipments) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->resolver()->resolveMany($shipments);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->assertSame($count($one), $count($many));
    }

    public function test_resolve_many_matches_resolve_keyed_by_shipment_id(): void
    {
        [$a, $b, $c] = $this->resolverCurrencies(3);
        $shipments = collect([
            $this->resolverShipment($a, null, $b->english_name),
            $this->resolverShipment($a),
            $this->resolverShipment(null, $c),
            $this->resolverShipment(),
        ]);

        $many = $this->resolver()->resolveMany($shipments);

        $this->assertSame($shipments->pluck('id')->all(), $many->keys()->all());

        foreach ($shipments as $shipment) {
            $this->assertSame($this->resolver()->resolve($shipment), $many[$shipment->id]);
        }
    }

    public function test_workflow_order_is_used_when_stage_orders_exist(): void
    {
        $type = 'Report Stage Test '.uniqid();
        $second = $this->stagesStatus($type, 'B '.uniqid(), 2);
        $first = $this->stagesStatus($type, 'A '.uniqid(), 1);
        $unordered = $this->stagesStatus($type, 'Unordered '.uniqid());

        $this->assertSame([$first->id, $second->id, $unordered->id], $this->stages()->ordered($type)->pluck('id')->all());
    }

    public function test_a_partial_stage_order_still_lists_every_other_live_row_and_keeps_arrived_statuses(): void
    {
        $type = Shipment::TYPE_TRACKING_STATUS;
        $this->clearType($type);
        $transit = $this->stagesStatus($type, TrackingStatus::InTransit->value, 1);
        $arrived = $this->stagesStatus($type, TrackingStatus::ArrivedAtDestination->value);
        $delivered = $this->stagesStatus($type, 'Delivered');

        $ids = $this->stages()->ordered($type)->pluck('id')->all();

        $this->assertSame([$transit->id, $arrived->id, $delivered->id], $ids);

        ReportStages::flush();
        $arrivedIds = CustomChecks::arrivedOrLaterIds();

        $this->assertContains($arrived->id, $arrivedIds);
        $this->assertContains($delivered->id, $arrivedIds);
        $this->assertNotContains($transit->id, $arrivedIds);
    }

    public function test_the_custom_status_lists_are_memoised_until_the_stages_are_flushed(): void
    {
        CustomChecks::validIdList();
        CustomChecks::clearedIds(new ReportFilters);
        DB::enableQueryLog();
        DB::flushQueryLog();
        CustomChecks::validIdList();
        CustomChecks::clearedIds(new ReportFilters);
        $this->assertSame(0, count(DB::getQueryLog()));
        ReportStages::flush();
        DB::flushQueryLog();
        CustomChecks::validIdList();
        $this->assertGreaterThan(0, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_no_report_sql_uses_a_mysql_eight_reserved_word_as_an_alias(): void
    {
        $reserved = 'rank|groups|system|lead|lag|row|rows|window|over|empty|of|cube|function|lateral|grouping|json_table|except|recursive|dense_rank|ntile|percent_rank|first_value|last_value|nth_value|cume_dist|row_number|array|member|persist|persist_only|role|virtual|stored';
        $offenders = [];

        foreach ([app_path('Services/Reports'), app_path('Filament/Pages/Reports')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                if ($file->getExtension() === 'php' && preg_match('/\bas\s+('.$reserved.')\b/i', $file->getContents(), $match)) {
                    $offenders[] = $file->getFilename().': '.$match[0];
                }
            }
        }

        $this->assertSame([], $offenders);
    }

    public function test_the_idle_days_filter_accepts_one_to_365_and_falls_back_to_the_default(): void
    {
        $this->assertSame(30, ReportFilters::fromArray(['idle_days' => 30])->idleDays);
        $this->assertSame(ReportFilters::IDLE_DAYS, ReportFilters::fromArray(['idle_days' => 999999])->idleDays);
        $this->assertSame(ReportFilters::IDLE_DAYS, ReportFilters::fromArray(['idle_days' => 0])->idleDays);
    }

    public function test_only_a_bank_profile_save_flushes_the_fx_rate_cache(): void
    {
        $version = fn (): int => (int) Cache::get('reports:fx:version', 0);
        $before = $version();

        RegisteredOrder::factory()->create();
        $this->assertSame($before, $version());

        BankProfile::factory()->create();
        $this->assertGreaterThan($before, $version());
    }

    public function test_fallback_order_maps_live_rows_skips_missing_and_appends_extras_by_id(): void
    {
        $type = BankProfile::TYPE_BANK_PROFILE;
        $this->clearType($type);
        $received = $this->stagesStatus($type, 'Received');
        $extraB = $this->stagesStatus($type, 'Extra B '.uniqid());
        $allocated = $this->stagesStatus($type, 'Allocated');
        $rejected = $this->stagesStatus($type, 'Rejected');
        $extraA = $this->stagesStatus($type, 'Extra A '.uniqid());

        $ids = $this->stages()->ordered($type)->pluck('id')->all();

        $this->assertSame([$allocated->id, $received->id, $extraB->id, $extraA->id], $ids);
        $this->assertNotContains($rejected->id, $ids);
    }

    public function test_fallback_exists_for_clearance_and_tracking_types(): void
    {
        $this->clearType(Custom::TYPE_CLEARANCE_STATUS);
        $this->clearType(Shipment::TYPE_TRACKING_STATUS);
        $completed = $this->stagesStatus(Custom::TYPE_CLEARANCE_STATUS, 'Completed');
        $declared = $this->stagesStatus(Custom::TYPE_CLEARANCE_STATUS, 'Declared');
        $delivered = $this->stagesStatus(Shipment::TYPE_TRACKING_STATUS, 'Delivered');
        $transit = $this->stagesStatus(Shipment::TYPE_TRACKING_STATUS, TrackingStatus::InTransit->value);

        $this->assertSame([$declared->id, $completed->id], $this->stages()->ordered(Custom::TYPE_CLEARANCE_STATUS)->pluck('id')->all());
        $this->assertSame([$transit->id, $delivered->id], $this->stages()->ordered(Shipment::TYPE_TRACKING_STATUS)->pluck('id')->all());
    }

    public function test_unknown_type_without_stage_order_lists_all_live_rows_by_id(): void
    {
        $type = 'Report Stage Test '.uniqid();
        $one = $this->stagesStatus($type, 'One '.uniqid());
        $two = $this->stagesStatus($type, 'Two '.uniqid());
        $this->stagesStatus($type, 'Gone '.uniqid())->delete();

        $this->assertSame([$one->id, $two->id], $this->stages()->ordered($type)->pluck('id')->all());
    }

    public function test_index_is_zero_based_and_excludes_separate_statuses(): void
    {
        $type = BankProfile::TYPE_BANK_PROFILE;
        $this->clearType($type);
        $ready = $this->stagesStatus($type, 'Ready for Allocation');
        $rejected = $this->stagesStatus($type, 'Rejected');
        $allocated = $this->stagesStatus($type, 'Allocated');

        $this->assertSame(0, $this->stages()->index($ready));
        $this->assertSame(1, $this->stages()->index($allocated));
        $this->assertNull($this->stages()->index($rejected));
    }

    public function test_is_at_or_after_truth_table(): void
    {
        $type = BankProfile::TYPE_BANK_PROFILE;
        $this->clearType($type);
        $ready = $this->stagesStatus($type, 'Ready for Allocation');
        $allocated = $this->stagesStatus($type, 'Allocated');
        $received = $this->stagesStatus($type, 'Received');
        $rejected = $this->stagesStatus($type, 'Rejected');

        $this->assertTrue($this->stages()->isAtOrAfter($allocated, 'Allocated'));
        $this->assertTrue($this->stages()->isAtOrAfter($received, 'Allocated'));
        $this->assertFalse($this->stages()->isAtOrAfter($ready, 'Allocated'));
        $this->assertFalse($this->stages()->isAtOrAfter($rejected, 'Ready for Allocation'));
        $this->assertFalse($this->stages()->isAtOrAfter($received, 'Rejected'));
        $this->assertFalse($this->stages()->isAtOrAfter($received, 'No Such Status'));
    }

    public function test_status_by_name_finds_live_row_and_returns_null_otherwise(): void
    {
        $type = 'Report Stage Test '.uniqid();
        $name = 'Named '.uniqid();
        $status = $this->stagesStatus($type, $name);

        $this->assertSame($status->id, $this->stages()->statusByName($type, $name)->id);
        $this->assertNull($this->stages()->statusByName($type, 'Missing '.uniqid()));
    }

    public function test_in_transit_and_arrived_lookups(): void
    {
        $type = Shipment::TYPE_TRACKING_STATUS;
        $this->clearType($type);

        $this->assertNull($this->stages()->inTransit());
        $this->assertNull($this->stages()->arrived());

        ReportStages::flush();
        $transit = $this->stagesStatus($type, TrackingStatus::InTransit->value);
        $arrived = $this->stagesStatus($type, TrackingStatus::ArrivedAtDestination->value);

        $this->assertSame($transit->id, $this->stages()->inTransit()->id);
        $this->assertSame($arrived->id, $this->stages()->arrived()->id);
    }

    public function test_empty_and_null_input_yield_an_empty_filter(): void
    {
        foreach ([null, []] as $input) {
            $filters = ReportFilters::fromArray($input);

            $this->assertNull($filters->dateFrom);
            $this->assertNull($filters->dateUntil);
            $this->assertNull($filters->sellerId);
            $this->assertNull($filters->supplySource);
            $this->assertSame([], $filters->statusIds);
            $this->assertNull($filters->categoryIds());
        }
    }

    public function test_forged_and_garbage_values_never_throw(): void
    {
        $filters = ReportFilters::fromArray([
            'date_from' => ['x'],
            'date_until' => 'not a date at all',
            'seller_id' => 'abc',
            'buyer_id' => ['1'],
            'category_id' => -4,
            'product_id' => '0',
            'currency_id' => 1.5,
            'registered_order_id' => '12; DROP TABLE x',
            'cost_center_id' => true,
            'bank_id' => null,
            'company_id' => new \stdClass,
            'supply_source' => "x'; --",
            'status_id' => 'nope',
            'unknown_key' => 'ignored',
        ]);

        $expected = [
            'date_from' => null,
            'date_until' => null,
            'seller_id' => null,
            'buyer_id' => null,
            'category_id' => null,
            'product_id' => null,
            'currency_id' => null,
            'registered_order_id' => null,
            'cost_center_id' => null,
            'bank_id' => null,
            'company_id' => null,
            'supply_source' => null,
            'status_id' => [],
        ];

        $this->assertSame($expected, array_intersect_key($filters->toArray(), $expected));
    }

    public function test_numeric_strings_and_ints_are_accepted(): void
    {
        $filters = ReportFilters::fromArray(['seller_id' => '7', 'buyer_id' => 8, 'supply_source' => 'export_self']);

        $this->assertSame(7, $filters->sellerId);
        $this->assertSame(8, $filters->buyerId);
        $this->assertSame('export_self', $filters->supplySource);
    }

    public function test_dates_are_normalised_to_day_boundaries(): void
    {
        $filters = ReportFilters::fromArray(['date_from' => '2026-03-10 15:30:00', 'date_until' => '2026-03-20']);

        $this->assertSame('2026-03-10 00:00:00', $filters->dateFrom->toDateTimeString());
        $this->assertSame('2026-03-20 23:59:59', $filters->dateUntil->toDateTimeString());
    }

    public function test_reversed_dates_are_swapped(): void
    {
        $filters = ReportFilters::fromArray(['date_from' => '2026-05-01', 'date_until' => '2026-04-01']);

        $this->assertSame('2026-04-01 00:00:00', $filters->dateFrom->toDateTimeString());
        $this->assertSame('2026-05-01 23:59:59', $filters->dateUntil->toDateTimeString());
    }

    public function test_status_ids_keep_only_unique_positive_ints(): void
    {
        $filters = ReportFilters::fromArray(['status_id' => ['3', 3, 'x', -1, 0, [5], 9, null]]);

        $this->assertSame([3, 9], $filters->statusIds);
    }

    public function test_to_array_round_trips(): void
    {
        $filters = ReportFilters::fromArray([
            'date_from' => '2026-01-02',
            'date_until' => '2026-02-03',
            'seller_id' => 1,
            'buyer_id' => 2,
            'category_id' => 3,
            'product_id' => 4,
            'currency_id' => 5,
            'registered_order_id' => 6,
            'cost_center_id' => 7,
            'bank_id' => 8,
            'company_id' => 9,
            'supply_source' => 'nimayi',
            'status_id' => [10, 11],
        ]);

        $copy = ReportFilters::fromArray($filters->toArray());

        $this->assertEquals($filters, $copy);
        $this->assertSame($filters->toArray(), $copy->toArray());
    }

    public function test_cache_key_is_stable_and_discriminating(): void
    {
        $a = ReportFilters::fromArray(['seller_id' => 1, 'status_id' => [2, 3]]);
        $b = ReportFilters::fromArray(['status_id' => ['2', '3'], 'seller_id' => '1', 'junk' => 'x']);
        $c = ReportFilters::fromArray(['seller_id' => 2, 'status_id' => [2, 3]]);

        $this->assertSame($a->cacheKey(), $b->cacheKey());
        $this->assertNotSame($a->cacheKey(), $c->cacheKey());
        $this->assertNotSame($a->cacheKey(), ReportFilters::fromArray(null)->cacheKey());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $a->cacheKey());
    }

    public function test_category_ids_return_self_and_descendants_only(): void
    {
        $root = Category::factory()->create(['level' => 0]);
        $child = Category::factory()->forParent($root)->create();
        $leaf = Category::factory()->forParent($child)->create();
        $sibling = Category::factory()->forParent($root)->create();

        $ids = ReportFilters::fromArray(['category_id' => $child->id])->categoryIds();

        $this->assertEqualsCanonicalizing([$child->id, $leaf->id], $ids);

        $rootIds = ReportFilters::fromArray(['category_id' => $root->id])->categoryIds();
        $this->assertEqualsCanonicalizing([$root->id, $child->id, $leaf->id, $sibling->id], $rootIds);

        $this->assertSame([$leaf->id], ReportFilters::fromArray(['category_id' => $leaf->id])->categoryIds());
    }

    public function test_unknown_category_resolves_to_itself(): void
    {
        $this->assertSame([987654321], ReportFilters::fromArray(['category_id' => 987654321])->categoryIds());
    }

    public function test_the_file_starts_with_a_bom_and_the_header_follows_the_label_order(): void
    {
        ReportCsvWriter::write([], ['b' => 'Second', 'a' => 'First'], $this->csvPath);

        $this->assertStringStartsWith("\xEF\xBB\xBF", (string) file_get_contents($this->csvPath));
        $this->assertSame(['Second,First'], $this->csvLines());
    }

    public function test_rows_are_restricted_to_the_label_keys_in_label_order(): void
    {
        $count = ReportCsvWriter::write([['a' => '1', 'secret' => 'x', 'b' => '2']], ['b' => 'B', 'a' => 'A'], $this->csvPath);

        $this->assertSame(1, $count);
        $this->assertSame(['B,A', '2,1'], $this->csvLines());
        $this->assertStringNotContainsString('secret', (string) file_get_contents($this->csvPath));
    }

    public function test_missing_null_boolean_and_array_values_are_stringified(): void
    {
        ReportCsvWriter::write([['a' => null, 'c' => true, 'd' => ['x', 'y']]], ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'], $this->csvPath);

        $this->assertSame(['', '', '1', 'x | y'], str_getcsv($this->csvLines()[1]));
    }

    public function test_formula_prefixes_are_neutralised_but_negative_numbers_stay_numeric(): void
    {
        ReportCsvWriter::write([['a' => '=cmd|/c calc', 'b' => '-12.5', 'c' => '@SUM(1)', 'd' => '+cmd']], ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'], $this->csvPath);

        $csv = (string) file_get_contents($this->csvPath);

        $this->assertStringNotContainsString(',=cmd', $csv);
        $this->assertStringContainsString("'=cmd|/c calc", $csv);
        $this->assertStringContainsString("'@SUM(1)", $csv);
        $this->assertStringContainsString("'+cmd", $csv);
        $this->assertStringContainsString(',-12.5,', $csv);
    }

    public function test_the_row_count_covers_generators_and_an_empty_set(): void
    {
        $generator = (function () {
            foreach (range(1, 5) as $n) {
                yield ['a' => $n];
            }
        })();

        $this->assertSame(5, ReportCsvWriter::write($generator, ['a' => 'A'], $this->csvPath));
        $this->assertCount(6, $this->csvLines());
        $this->assertSame(0, ReportCsvWriter::write([], ['a' => 'A'], $this->csvPath));
    }

    public function test_all_collects_the_bank_profile_checks_and_for_report_filters_by_report_key(): void
    {
        $all = $this->registry()->all();
        $this->assertSame($all->count(), $all->unique('key')->count());
        $this->assertGreaterThan(0, $all->count());
        $this->assertCount(15, $this->registry()->forReport('fx_supply'));
        $this->assertCount(0, $this->registry()->forReport('unknown_report'));
        $this->assertContainsOnlyInstancesOf(Check::class, $all);
    }

    public function test_message_and_expected_keys_follow_the_lang_contract(): void
    {
        $check = $this->check(self::ZERO);

        $this->assertSame('resources/report/strings.attention.checks.bank_profile.requested_amount_zero.message', $check->messageKey());
        $this->assertSame('resources/report/strings.attention.checks.bank_profile.requested_amount_zero.expected', $check->expectedKey());
    }

    public function test_evaluate_returns_the_documented_row_shape(): void
    {
        $this->userWith(['bank_profile.edit']);
        $profile = $this->zero();

        $row = $this->registry()->evaluate($this->check(self::ZERO), $this->filters())->first();

        $this->assertEqualsCanonicalizing(['id', 'label', 'url', 'problem', 'offending', 'expected', 'severity', 'changed_by', 'changed_at', 'check'], array_keys($row));
        $this->assertSame($profile->id, $row['id']);
        $this->assertSame($profile->bp_number, $row['label']);
        $this->assertSame(CalendarModules::url($profile), $row['url']);
        $this->assertSame(__('resources/report/strings.attention.checks.bank_profile.requested_amount_zero.message'), $row['problem']);
        $this->assertSame('0', $row['offending']);
        $this->assertSame(__('resources/report/strings.attention.checks.bank_profile.requested_amount_zero.expected'), $row['expected']);
        $this->assertSame('error', $row['severity']);
        $this->assertSame(self::ZERO, $row['check']);
    }

    public function test_the_url_is_withheld_when_the_viewer_cannot_edit(): void
    {
        $this->userWith(['bank_profile.view']);
        $this->zero();

        $this->assertNull($this->registry()->evaluate($this->check(self::ZERO), $this->filters())->first()['url']);
    }

    public function test_compliant_profiles_produce_no_rows(): void
    {
        $this->checksProfile();

        $this->assertCount(0, $this->registry()->evaluate($this->check(self::ZERO), $this->filters()));
    }

    public function test_the_audit_path_uses_a_constant_number_of_queries(): void
    {
        $this->userWith(['bank_profile.edit'])->can('bank_profile.edit');
        $other = RegisteredOrder::factory()->create();
        $third = RegisteredOrder::factory()->create();
        $this->zero();
        collect(range(1, 6))->each(fn () => $this->zero($other));
        $this->registry()->evaluate($this->check(self::ZERO), $this->filters($third));

        $one = $this->queries(fn () => $this->registry()->evaluate($this->check(self::ZERO), $this->filters()));
        $many = $this->queries(fn () => $this->registry()->evaluate($this->check(self::ZERO), $this->filters($other)));

        $this->assertSame($one, $many);
    }

    public function test_the_status_history_path_uses_a_constant_number_of_queries(): void
    {
        $this->userWith(['bank_profile.edit'])->can('bank_profile.edit');
        $other = RegisteredOrder::factory()->create();
        $third = RegisteredOrder::factory()->create();
        $this->stuck();
        collect(range(1, 6))->each(fn () => $this->stuck($other));
        $this->registry()->evaluate($this->check(self::STUCK), $this->filters($third));

        $one = $this->queries(fn () => $this->registry()->evaluate($this->check(self::STUCK), $this->filters()));
        $many = $this->queries(fn () => $this->registry()->evaluate($this->check(self::STUCK), $this->filters($other)));

        $this->assertSame($one, $many);
        $this->assertCount(6, $this->registry()->evaluate($this->check(self::STUCK), $this->filters($other)));
    }

    public function test_who_and_when_come_from_the_latest_audit_row(): void
    {
        $first = User::factory()->create(['name' => 'Audit First '.uniqid()]);
        $second = User::factory()->create(['name' => 'Audit Second '.uniqid()]);
        $profile = $this->zero();
        activity('audit')->performedOn($profile)->causedBy($first)->log('updated');
        $latest = activity('audit')->performedOn($profile)->causedBy($second)->log('updated');
        activity('other_log')->performedOn($profile)->causedBy($first)->log('updated');

        $row = $this->registry()->evaluate($this->check(self::ZERO), $this->filters())->first();

        $this->assertSame($second->name, $row['changed_by']);
        $this->assertSame($latest->created_at->toDateTimeString(), $row['changed_at']->toDateTimeString());
    }

    public function test_who_and_when_are_null_without_any_audit_row(): void
    {
        $profile = $this->zero();
        DB::table('activity_log')->where('subject_type', BankProfile::class)->where('subject_id', $profile->id)->delete();
        CheckRegistry::invalidate();

        $row = $this->registry()->evaluate($this->check(self::ZERO), $this->filters())->first();

        $this->assertNull($row['changed_by']);
        $this->assertNull($row['changed_at']);
    }

    public function test_who_and_when_for_status_columns_come_from_the_latest_status_history(): void
    {
        $user = User::factory()->create(['name' => 'History Winner '.uniqid()]);
        $profile = $this->stuck();
        $winner = StatusHistory::create(['statusable_type' => BankProfile::class, 'statusable_id' => $profile->id, 'field' => 'status_id', 'to_status_id' => $profile->status_id, 'user_id' => $user->id]);
        StatusHistory::whereKey($winner->id)->update(['created_at' => now()->subDays(41)]);
        CheckRegistry::invalidate();

        $row = $this->registry()->evaluate($this->check(self::STUCK), $this->filters())->first();

        $this->assertSame($user->name, $row['changed_by']);
        $this->assertSame(now()->subDays(41)->toDateString(), $row['changed_at']->toDateString());
    }

    public function test_a_model_save_refreshes_the_cached_rows(): void
    {
        $first = $this->zero();
        $check = $this->check(self::ZERO);

        $this->assertSame([$first->id], $this->registry()->evaluate($check, $this->filters())->pluck('id')->all());

        $this->zero();

        $this->assertCount(2, $this->registry()->evaluate($check, $this->filters()));
    }

    public function test_the_observer_invalidates_on_saved_deleted_and_restored(): void
    {
        $check = $this->check(self::ZERO);
        $profile = $this->checksProfile();

        $this->assertCount(0, $this->registry()->evaluate($check, $this->filters()));

        $profile->update(['requested_amount' => 0]);
        $this->assertCount(1, $this->registry()->evaluate($check, $this->filters()));

        $profile->delete();
        $this->assertCount(0, $this->registry()->evaluate($check, $this->filters()));

        $profile->restore();
        $this->assertCount(1, $this->registry()->evaluate($check, $this->filters()));
    }

    public function test_direct_inserts_without_events_stay_cached_until_invalidate(): void
    {
        $check = $this->check(self::ZERO);
        $this->assertCount(0, $this->registry()->evaluate($check, $this->filters()));

        $profile = $this->zero();
        CheckRegistry::invalidate();
        $this->assertSame([$profile->id], $this->registry()->evaluate($check, $this->filters())->pluck('id')->all());

        DB::table('bank_profiles')->where('id', $profile->id)->update(['requested_amount' => 10]);
        $this->assertCount(1, $this->registry()->evaluate($check, $this->filters()));

        CheckRegistry::invalidate();
        $this->assertCount(0, $this->registry()->evaluate($check, $this->filters()));
    }

    public function test_the_cache_is_separate_per_user_and_per_filter_set(): void
    {
        $other = RegisteredOrder::factory()->create();
        $mine = $this->zero();
        $theirs = $this->zero($other);
        $check = $this->check(self::ZERO);

        $this->assertSame([$mine->id], $this->registry()->evaluate($check, $this->filters())->pluck('id')->all());
        $this->assertSame([$theirs->id], $this->registry()->evaluate($check, $this->filters($other))->pluck('id')->all());

        $this->userWith(['bank_profile.edit']);
        $this->assertNotNull($this->registry()->evaluate($check, $this->filters())->first()['url']);
    }

    public function test_counts_match_the_evaluated_rows_per_severity(): void
    {
        $this->zero();
        $this->checksProfile(['purchased_equivalent' => 0]);
        $this->checksProfile(['purchase_date' => null]);
        $this->stuck();
        $this->checksProfile(['payment_due_date' => today()->subDay()->toDateString()]);
        StatusHistory::where('statusable_type', BankProfile::class)->where('statusable_id', $this->checksProfile()->id)->delete();
        $filters = $this->filters();

        $expected = ['error' => 0, 'warning' => 0, 'info' => 0];
        foreach ($this->registry()->forReport('fx_supply') as $check) {
            $expected[$check->severity] += $this->registry()->evaluate($check, $filters)->count();
        }

        $this->assertSame($expected, $this->registry()->counts('fx_supply', $filters));
        $this->assertGreaterThan(0, $expected['error']);
        $this->assertGreaterThan(0, $expected['warning']);
        $this->assertGreaterThan(0, $expected['info']);
    }

    public function test_counts_for_an_unknown_report_are_all_zero(): void
    {
        $this->assertSame(['error' => 0, 'warning' => 0, 'info' => 0], $this->registry()->counts('unknown_report', $this->filters()));
    }

    public function test_counts_use_a_constant_number_of_queries(): void
    {
        $this->checksOrder();
        $other = RegisteredOrder::factory()->create();
        collect(range(1, 5))->each(fn () => $this->zero($other));
        $this->registry()->counts('fx_supply', $this->filters(RegisteredOrder::factory()->create()));

        $empty = $this->queries(fn () => $this->registry()->counts('fx_supply', $this->filters()));
        $full = $this->queries(fn () => $this->registry()->counts('fx_supply', $this->filters($other)));

        $this->assertSame($empty, $full);
    }

    public function test_for_record_returns_only_the_checks_the_record_offends(): void
    {
        $record = $this->checksProfile(['requested_amount' => 0, 'purchased_equivalent' => 0]);

        $keys = $this->registry()->forRecord($record)->pluck('key')->all();

        $this->assertContains(self::ZERO, $keys);
        $this->assertContains('bank_profile.purchased_without_equivalent', $keys);
        $this->assertNotContains('bank_profile.purchased_without_currency', $keys);
        $this->assertNotContains(self::STUCK, $keys);
    }

    public function test_for_record_is_empty_for_a_model_without_checks(): void
    {
        $this->assertCount(0, $this->registry()->forRecord(User::factory()->create()));
    }

    public function test_for_record_does_not_leak_other_records(): void
    {
        $this->zero();
        $clean = $this->checksProfile();

        $this->assertNotContains(self::ZERO, $this->registry()->forRecord($clean)->pluck('key')->all());
    }

    public function test_for_report_hides_checks_of_models_the_viewer_cannot_view(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'checks_gate_role_'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'report.view_fx_supply', 'guard_name' => 'web']));
        $user->assignRole($role);
        $this->actingAs($user);

        $this->assertCount(0, $this->registry()->forReport('fx_supply'));

        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'bank_profile.view', 'guard_name' => 'web']));
        $user->unsetRelation('roles')->unsetRelation('permissions');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertCount(15, $this->registry()->forReport('fx_supply'));
    }

    public function test_correspondence_checks_are_open_to_every_report_viewer(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'checks_open_role_'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'report.view_operations', 'guard_name' => 'web']));
        $user->assignRole($role);
        $this->actingAs($user);

        $keys = $this->registry()->forReport('operations')->pluck('key')->all();

        $this->assertContains('correspondence.open_high_priority', $keys);
        $this->assertNotContains('shipment.missing_tracking_status', $keys);
    }

    public function test_the_quick_filter_is_sanitised_travels_with_the_filters_but_never_enters_the_cache_key(): void
    {
        $with = ReportFilters::fromArray(['quick' => 'queued_currency_12', 'registered_order_id' => 5]);
        $without = ReportFilters::fromArray(['registered_order_id' => 5]);

        $this->assertSame('queued_currency_12', $with->quick);
        $this->assertSame('queued_currency_12', $with->toArray()['quick']);
        $this->assertSame('queued_currency_12', ReportFilters::fromArray($with->toArray())->quick);
        $this->assertSame($without->cacheKey(), $with->cacheKey());
        $this->assertNull(ReportFilters::fromArray(['quick' => 'bad key; drop table'])->quick);
        $this->assertNull(ReportFilters::fromArray(['quick' => ['x']])->quick);
    }

    public function test_typed_whole_numbers_arrive_as_floats_and_are_accepted_for_the_day_filters(): void
    {
        $filters = ReportFilters::fromArray(['idle_days' => 3.0, 'stuck_days' => 45.0]);

        $this->assertSame(3, $filters->idleDays);
        $this->assertSame(45, $filters->stuckDays);
        $this->assertNull(ReportFilters::fromArray(['stuck_days' => 45.5])->stuckDays);
    }

    public function test_every_threshold_has_its_documented_default_and_the_legacy_fields_keep_working(): void
    {
        $filters = new ReportFilters;

        $this->assertSame(['idle_error' => 14, 'validity' => 30, 'supply' => 30, 'stuck' => 30, 'due_soon' => 14, 'arrive_1' => 7, 'arrive_2' => 14, 'late_error' => 14, 'late_grace' => 0, 'outlier_factor' => 3, 'supply_full' => 99, 'top_n' => 5, 'exceed_pct' => 1, 'commit_due' => 30], ReportFilters::THRESHOLD_DEFAULTS);

        foreach (ReportFilters::THRESHOLD_DEFAULTS as $key => $default) {
            $this->assertSame($default, $filters->threshold($key), $key);
        }

        $this->assertSame(ReportFilters::IDLE_DAYS, $filters->threshold('idle'));
        $this->assertSame(45, ReportFilters::fromArray(['stuck_days' => 45])->threshold('stuck'));
        $this->assertSame(3, ReportFilters::fromArray(['idle_days' => 3])->threshold('idle'));
        $this->assertSame(30, ReportFilters::fromArray(['stuck_days' => 'abc'])->threshold('stuck'));
    }

    public function test_threshold_values_travel_as_th_keys_are_sanitised_and_round_trip(): void
    {
        $filters = ReportFilters::fromArray(['th_idle_error' => '21', 'th_validity' => 9.0, 'th_late_grace' => 0, 'th_supply' => 400, 'th_due_soon' => 0, 'th_arrive_1' => 'x', 'th_stuck' => 5, 'th_unknown' => 3, 'th_arrive_2' => [1]]);

        $this->assertSame(['idle_error' => 21, 'validity' => 9, 'late_grace' => 0], $filters->thresholds);
        $this->assertSame(21, $filters->threshold('idle_error'));
        $this->assertSame(30, $filters->threshold('supply'));
        $this->assertSame(14, $filters->threshold('due_soon'));
        $this->assertSame(30, $filters->threshold('stuck'));
        $this->assertSame(0, $filters->threshold('late_grace'));
        $this->assertSame(21, $filters->toArray()['th_idle_error']);
        $this->assertNull($filters->toArray()['th_supply']);
        $this->assertArrayNotHasKey('th_stuck', $filters->toArray());
        $this->assertSame($filters->toArray(), ReportFilters::fromArray($filters->toArray())->toArray());
        $this->assertNotSame((new ReportFilters)->cacheKey(), $filters->cacheKey());
        $this->assertSame(1, ReportFilters::fromArray(['th_late_error' => 1])->threshold('late_error'));
        $this->assertSame(14, ReportFilters::fromArray(['th_late_grace' => 0])->threshold('late_error'));
        $this->assertSame(365, ReportFilters::fromArray(['th_validity' => 365])->threshold('validity'));
        $this->assertSame(30, ReportFilters::fromArray(['th_validity' => 366])->threshold('validity'));
    }

    public function test_an_unknown_threshold_name_is_a_programming_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ReportFilters)->threshold('nope');
    }

    public function test_the_date_basis_and_include_toggles_default_and_sanitise(): void
    {
        $default = new ReportFilters;

        $this->assertNull($default->dateBasis);
        $this->assertTrue($default->includeUndated);
        $this->assertFalse($default->includeVoid);
        $this->assertTrue(ReportFilters::fromArray([])->includeUndated);
        $this->assertFalse(ReportFilters::fromArray(['include_undated' => false])->includeUndated);
        $this->assertFalse(ReportFilters::fromArray(['include_undated' => '0'])->includeUndated);
        $this->assertTrue(ReportFilters::fromArray(['include_void' => 'true'])->includeVoid);
        $this->assertSame('purchase_date', ReportFilters::fromArray(['date_basis' => 'purchase_date'])->dateBasis);
        $this->assertNull(ReportFilters::fromArray(['date_basis' => 'a b; drop'])->dateBasis);

        $round = ReportFilters::fromArray(['date_basis' => 'eta', 'include_undated' => false, 'include_void' => true, 'purchased_currency_id' => 4]);

        $this->assertSame($round->toArray(), ReportFilters::fromArray($round->toArray())->toArray());
        $this->assertSame(4, $round->purchasedCurrencyId);
    }

    public function test_the_departure_basis_and_shipment_status_filters_default_sanitise_and_round_trip(): void
    {
        $default = new ReportFilters;

        $this->assertNull($default->departureBasis);
        $this->assertSame([], $default->shipmentStatusIds);

        foreach (ReportFilters::DEPARTURE_BASES as $basis) {
            $this->assertSame($basis, ReportFilters::fromArray(['departure_basis' => $basis])->departureBasis);
        }

        $this->assertNull(ReportFilters::fromArray(['departure_basis' => 'drop table'])->departureBasis);
        $this->assertNull(ReportFilters::fromArray(['departure_basis' => ['x']])->departureBasis);
        $this->assertSame([3, 4], ReportFilters::fromArray(['shipment_status_id' => ['3', 4, 'x', 0, 3]])->shipmentStatusIds);
        $this->assertSame([], ReportFilters::fromArray(['shipment_status_id' => 'abc'])->shipmentStatusIds);

        $round = ReportFilters::fromArray(['departure_basis' => 'etd_too', 'shipment_status_id' => [5]]);

        $this->assertSame($round->toArray(), ReportFilters::fromArray($round->toArray())->toArray());
        $this->assertNotSame((new ReportFilters)->cacheKey(), $round->cacheKey());
        $this->assertSame([7, 14], \App\Services\Reports\Checks\InTransitChecks::arriveWindows(new ReportFilters));
    }

    public function test_the_requester_filter_defaults_sanitises_round_trips_and_changes_the_cache_key(): void
    {
        $this->assertNull((new ReportFilters)->requesterId);
        $this->assertNull(ReportFilters::fromArray(['requester_id' => 'abc'])->requesterId);
        $this->assertNull(ReportFilters::fromArray(['requester_id' => 0])->requesterId);
        $this->assertNull(ReportFilters::fromArray(['requester_id' => [3]])->requesterId);
        $this->assertSame(9, ReportFilters::fromArray(['requester_id' => '9'])->requesterId);

        $round = ReportFilters::fromArray(['requester_id' => 9, 'th_late_grace' => 0, 'th_arrive_1' => 3]);

        $this->assertSame(9, $round->toArray()['requester_id']);
        $this->assertSame($round->toArray(), ReportFilters::fromArray($round->toArray())->toArray());
        $this->assertNotSame((new ReportFilters)->cacheKey(), $round->cacheKey());
        $this->assertSame(0, $round->threshold('late_grace'));
        $this->assertSame(3, $round->threshold('arrive_1'));
    }

    public function test_the_cache_key_changes_with_the_day_so_cached_results_never_cross_midnight(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-03-01 23:59:00');
        $before = (new ReportFilters)->cacheKey();
        \Illuminate\Support\Carbon::setTestNow('2026-03-02 00:01:00');
        $after = (new ReportFilters)->cacheKey();
        \Illuminate\Support\Carbon::setTestNow('2026-03-02 22:00:00');
        $same = (new ReportFilters)->cacheKey();
        \Illuminate\Support\Carbon::setTestNow();

        $this->assertNotSame($before, $after);
        $this->assertSame($after, $same);
    }

    public function test_sql_today_is_a_php_built_date_literal_and_no_report_sql_uses_curdate(): void
    {
        $this->assertSame("'".today()->toDateString()."'", \App\Services\Reports\Support\SqlToday::literal());
        $this->assertMatchesRegularExpression("/^'\d{4}-\d{2}-\d{2}'$/", \App\Services\Reports\Support\SqlToday::literal());
        $this->assertStringContainsString(\App\Services\Reports\Support\SqlToday::literal(), \App\Services\Reports\Support\FileActivity::idleSql());

        foreach ([app_path('Services/Reports'), app_path('Filament/Pages/Reports')] as $directory) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $this->assertStringNotContainsStringIgnoringCase('curdate', (string) file_get_contents($file->getPathname()), $file->getFilename());
                }
            }
        }
    }

    public function test_the_void_sql_can_be_skipped_with_the_include_toggle(): void
    {
        $this->assertSame(\App\Services\Reports\Checks\InTransitChecks::voidSql(), \App\Services\Reports\Checks\InTransitChecks::voidSqlFor(new ReportFilters));
        $this->assertSame('(1 = 1)', \App\Services\Reports\Checks\InTransitChecks::voidSqlFor(new ReportFilters(includeVoid: true)));
    }

    public function test_the_shared_date_helper_follows_the_basis_the_range_and_the_undated_switch(): void
    {
        $columns = ['order_date' => 'registered_orders.order_date', 'validity_date' => 'registered_orders.validity_date'];
        $a = RegisteredOrder::factory()->create(['order_date' => '2026-01-10', 'validity_date' => '2026-06-10']);
        $b = RegisteredOrder::factory()->create(['order_date' => '2026-04-10', 'validity_date' => null]);
        $ids = fn (array $filters, string $default = 'order_date'): array => \App\Services\Reports\Support\ReportDates::apply(RegisteredOrder::query()->whereIn('id', [$a->id, $b->id]), ReportFilters::fromArray($filters), $columns, $default)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$a->id, $b->id], $ids([]));
        $this->assertSame([$b->id], $ids(['date_from' => '2026-02-01']));
        $this->assertSame([$a->id], $ids(['date_until' => '2026-02-01']));
        $this->assertSame([$a->id, $b->id], $ids(['date_from' => '2026-05-01', 'date_basis' => 'validity_date']));
        $this->assertSame([$a->id], $ids(['date_from' => '2026-05-01', 'date_basis' => 'validity_date', 'include_undated' => false]));
        $this->assertSame([$b->id], $ids(['date_from' => '2026-02-01', 'date_basis' => 'nonsense']));
        $this->assertSame([$a->id, $b->id], $ids(['date_from' => '2026-05-01'], 'validity_date'));
        $this->assertSame([$a->id], $ids(['date_from' => '2026-01-01', 'date_until' => '2026-01-31']));
    }

    public function test_the_registry_passes_the_filters_to_the_expected_text(): void
    {
        $order = $this->checksOrder();
        $profile = BankProfile::factory()->create(['registered_order_id' => $order->id, 'requested_amount' => 100, 'payment_due_date' => today()->addDays(6)->toDateString()]);
        $check = collect(\App\Services\Reports\Checks\BankProfileChecks::all())->firstWhere('key', 'bank_profile.payment_due_soon');
        $filters = ReportFilters::fromArray(['registered_order_id' => $order->id, 'th_due_soon' => 9]);

        $row = app(CheckRegistry::class)->evaluate($check, $filters)->firstWhere('id', $profile->id);

        $this->assertNotNull($row);
        $this->assertStringContainsString('9', $row['expected']);
    }

    public function test_the_filter_dates_parse_strictly_as_iso_days_and_never_read_a_jalali_looking_year_as_gregorian(): void
    {
        foreach (['tomorrow', 'next monday', '1405-07-28', '1403-12-30', '2026-02-30', '2026-13-01', '2026/03/01', '2026-3-1', '26-03-01', '', ' 2026-03-01', '2026-03', 20260301, ['2026-03-01'], 3.5, true] as $bad) {
            $this->assertNull(ReportFilters::fromArray(['date_from' => $bad, 'date_until' => $bad])->dateFrom, json_encode($bad));
            $this->assertNull(ReportFilters::fromArray(['date_from' => $bad])->dateUntil, json_encode($bad));
        }

        $this->assertSame('2026-03-10', ReportFilters::fromArray(['date_from' => '2026-03-10T15:30:00'])->dateFrom->toDateString());
        $this->assertSame('2026-03-10 00:00:00', ReportFilters::fromArray(['date_from' => '2026-03-10 23:59:59'])->dateFrom->toDateTimeString());
        $this->assertSame('2024-02-29', ReportFilters::fromArray(['date_until' => '2024-02-29'])->dateUntil->toDateString());
        $this->assertSame('23:59:59', ReportFilters::fromArray(['date_until' => '2024-02-29'])->dateUntil->format('H:i:s'));
        $this->assertNull(ReportFilters::fromArray(['date_until' => '2025-02-29'])->dateUntil);
    }

    public function test_the_filter_dates_round_trip_as_the_same_iso_text_in_both_calendars(): void
    {
        foreach (['jalali', 'gregorian'] as $calendar) {
            session(['calendar_type' => $calendar]);
            $filters = ReportFilters::fromArray(['date_from' => '2025-03-20', 'date_until' => '2026-03-21']);
            $again = ReportFilters::fromArray($filters->toArray());

            $this->assertSame('2025-03-20', $filters->toArray()['date_from'], $calendar);
            $this->assertSame('2026-03-21', $filters->toArray()['date_until'], $calendar);
            $this->assertSame($filters->toArray(), $again->toArray(), $calendar);
            $this->assertSame($filters->cacheKey(), $again->cacheKey(), $calendar);
        }

        session()->forget('calendar_type');
        $this->assertSame('1403-12-30', \Morilog\Jalali\Jalalian::fromCarbon(\Illuminate\Support\Carbon::parse('2025-03-20'))->format('Y-m-d'));
    }

    public function test_the_check_cache_key_includes_the_calendar_so_cached_date_texts_never_leak(): void
    {
        $order = RegisteredOrder::factory()->create();
        $shipment = Shipment::factory()->create(['registered_order_id' => $order->id, 'eta' => '2025-03-20', 'etd' => '2025-03-21', 'warehouse_date' => null, 'exit_date' => null, 'shipment_status_id' => null]);
        Custom::factory()->create(['registered_order_id' => $order->id, 'shipment_id' => $shipment->id, 'clearance_status_id' => null, 'doc_submission_date' => '2025-03-21', 'clearance_date' => '2025-03-20']);
        $filters = new ReportFilters(registeredOrderId: $order->id);
        $registry = app(CheckRegistry::class);
        $transit = collect(\App\Services\Reports\Checks\InTransitChecks::all())->firstWhere('key', 'shipment.eta_before_etd');
        $custom = collect(\App\Services\Reports\Checks\CustomChecks::all())->firstWhere('key', 'custom.doc_submission_after_clearance');
        $texts = [];

        foreach (['gregorian', 'jalali'] as $calendar) {
            session(['calendar_type' => $calendar]);

            $texts[$calendar] = [
                $registry->evaluate($transit, $filters)->firstWhere('id', $shipment->id)['offending'] ?? null,
                $registry->evaluate($custom, $filters)->first()['offending'] ?? null,
            ];
        }

        session()->forget('calendar_type');

        $this->assertSame(toGregorianDate('2025-03-20').' < '.toGregorianDate('2025-03-21'), $texts['gregorian'][0]);
        $this->assertSame(toPersianDate('2025-03-20').' < '.toPersianDate('2025-03-21'), $texts['jalali'][0]);
        $this->assertSame(toGregorianDate('2025-03-21').' > '.toGregorianDate('2025-03-20'), $texts['gregorian'][1]);
        $this->assertSame(toPersianDate('2025-03-21').' > '.toPersianDate('2025-03-20'), $texts['jalali'][1]);
        $this->assertNotSame($texts['gregorian'], $texts['jalali']);
    }

    public function test_the_shared_executive_and_commitment_filters_default_sanitise_clamp_round_trip_and_key_the_cache(): void
    {
        $default = ReportFilters::fromArray([]);

        $this->assertFalse($default->includeFlaggedInvoices);
        $this->assertFalse($default->includeOutliers);
        $this->assertSame('gross', $default->weightBasis);
        $this->assertSame('shipment', $default->viewMode);
        $this->assertFalse($default->fallbackOrderAmount);
        $this->assertSame('grand_total', $default->valueBasis);
        $this->assertSame('purchased', $default->amountIn);
        $this->assertNull($default->commitmentState);
        $this->assertSame(3, $default->threshold('outlier_factor'));
        $this->assertSame(99, $default->threshold('supply_full'));
        $this->assertSame(5, $default->threshold('top_n'));
        $this->assertSame(1, $default->threshold('exceed_pct'));
        $this->assertSame(30, $default->threshold('commit_due'));

        $set = ReportFilters::fromArray(['include_flagged_invoices' => '1', 'include_outliers' => true, 'weight_basis' => 'net', 'view_mode' => 'product', 'fallback_order_amount' => 'true', 'value_basis' => 'items_total', 'amount_in' => 'eur', 'commitment_state' => 'overdue', 'th_outlier_factor' => '5', 'th_supply_full' => 95, 'th_top_n' => 10, 'th_exceed_pct' => 0, 'th_commit_due' => 45]);

        $this->assertTrue($set->includeFlaggedInvoices);
        $this->assertTrue($set->includeOutliers);
        $this->assertSame('net', $set->weightBasis);
        $this->assertSame('product', $set->viewMode);
        $this->assertTrue($set->fallbackOrderAmount);
        $this->assertSame('items_total', $set->valueBasis);
        $this->assertSame('eur', $set->amountIn);
        $this->assertSame('overdue', $set->commitmentState);
        $this->assertSame([5, 95, 10, 0, 45], [$set->threshold('outlier_factor'), $set->threshold('supply_full'), $set->threshold('top_n'), $set->threshold('exceed_pct'), $set->threshold('commit_due')]);
        $this->assertSame($set->toArray(), ReportFilters::fromArray($set->toArray())->toArray());

        $keys = ['include_flagged_invoices', 'include_outliers', 'weight_basis', 'fallback_order_amount', 'value_basis', 'amount_in', 'commitment_state', 'th_outlier_factor', 'th_supply_full', 'th_top_n', 'th_exceed_pct', 'th_commit_due'];

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $set->toArray(), $key);
            $this->assertNotSame($default->cacheKey(), ReportFilters::fromArray([$key => $set->toArray()[$key]])->cacheKey(), $key);
        }

        $this->assertSame($default->cacheKey(), ReportFilters::fromArray(['view_mode' => 'product', 'quick' => 'delayed'])->cacheKey());
    }

    public function test_the_new_filter_values_are_clamped_to_their_bounds_and_forged_values_never_throw(): void
    {
        $filters = ReportFilters::fromArray(['th_supply_full' => 101, 'th_top_n' => 21, 'th_outlier_factor' => 0, 'th_exceed_pct' => 101, 'th_commit_due' => 366]);

        $this->assertSame([99, 5, 3, 1, 30], [$filters->threshold('supply_full'), $filters->threshold('top_n'), $filters->threshold('outlier_factor'), $filters->threshold('exceed_pct'), $filters->threshold('commit_due')]);
        $this->assertSame(100, ReportFilters::fromArray(['th_supply_full' => 100])->threshold('supply_full'));
        $this->assertSame(20, ReportFilters::fromArray(['th_top_n' => 20])->threshold('top_n'));
        $this->assertSame(0, ReportFilters::fromArray(['th_exceed_pct' => 0])->threshold('exceed_pct'));

        $forged = [['x'], 'drop table', 1.5, -1, null, true, new \stdClass];

        foreach (['include_flagged_invoices', 'include_outliers', 'fallback_order_amount', 'weight_basis', 'view_mode', 'value_basis', 'amount_in', 'commitment_state', 'th_outlier_factor', 'th_supply_full', 'th_top_n', 'th_exceed_pct', 'th_commit_due'] as $key) {
            foreach ($forged as $value) {
                $this->assertInstanceOf(ReportFilters::class, ReportFilters::fromArray([$key => $value]), $key);
            }
        }

        $bad = ReportFilters::fromArray(['weight_basis' => 'drop', 'view_mode' => ['x'], 'value_basis' => 1, 'amount_in' => 'usd', 'commitment_state' => 'nope']);

        $this->assertSame(['gross', 'shipment', 'grand_total', 'purchased', null], [$bad->weightBasis, $bad->viewMode, $bad->valueBasis, $bad->amountIn, $bad->commitmentState]);
    }

    public function test_saving_a_payment_or_purchase_order_invalidates_the_cached_report_numbers(): void
    {
        foreach ([Payment::class, PurchaseOrder::class] as $model) {
            SmartCacheManager::remember('ReportCheck', ['probe' => $model], 5, fn (): string => 'stale');
            $this->assertSame('stale', SmartCacheManager::remember('ReportCheck', ['probe' => $model], 5, fn (): string => 'fresh'));

            $model::factory()->create();

            $this->assertSame('fresh', SmartCacheManager::remember('ReportCheck', ['probe' => $model], 5, fn (): string => 'fresh'), $model);
        }
    }

    public function test_the_rate_basis_filter_is_sanitised_and_round_trips(): void
    {
        $this->assertSame('pair', ReportFilters::fromArray(['rate_basis' => 'bogus'])->rateBasis);
        $this->assertSame('order', ReportFilters::fromArray(['rate_basis' => 'order'])->rateBasis);
        $this->assertSame('order', ReportFilters::fromArray(ReportFilters::fromArray(['rate_basis' => 'order'])->toArray())->rateBasis);
        $this->assertNotSame(ReportFilters::fromArray([])->cacheKey(), ReportFilters::fromArray(['rate_basis' => 'order'])->cacheKey());
    }
}
