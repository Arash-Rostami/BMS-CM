<?php

namespace Tests\Feature\Services\Reports;

use App\Filament\Resources\CorrespondenceResource;
use App\Filament\Resources\CustomResource;
use App\Filament\Resources\Operational\BankProfileResource\Enums\Status as BankProfileStatus;
use App\Filament\Resources\Operational\CustomResource\Enums\ClearanceStatus;
use App\Filament\Resources\RegisteredOrderResource;
use App\Filament\Resources\ShipmentResource;
use App\Models\BankProfile;
use App\Models\Company;
use App\Models\Correspondence;
use App\Models\Currency;
use App\Models\Custom;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use App\Models\RegisteredOrderItem;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\Reports\Builders\OperationsReportService;
use App\Services\Reports\Checks\Check;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Checks\RegisteredOrderChecks;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\ActivityFeed;
use App\Services\Reports\Support\ReportFilters;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OperationsReportTest extends TestCase
{
    private Company $seller;

    private Status $completed;

    private ?Status $profileStatus = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        Queue::fake();
        ReportStages::flush();
        app()->setLocale('en');
        $this->seller = Company::factory()->create();
        $this->completed = $this->makeStatus(Custom::TYPE_CLEARANCE_STATUS, ClearanceStatus::Completed->value);
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
        return array_map(fn (string $key) => [$key], [
            'registered_order.idle_over_threshold',
            'registered_order.idle_over_14',
            'registered_order.validity_expiring',
            'registered_order.validity_expired',
            'registered_order.no_items',
            'registered_order.no_bank_profile_after_n_days',
            'registered_order.missing_validity_date',
            'registered_order.missing_party',
            'registered_order.missing_currency',
            'registered_order.in_customs_without_payment_or_bank_profile',
            'shipment.missing_tracking_status',
            'correspondence.open_high_priority',
        ]);
    }

    private function service(): OperationsReportService
    {
        return app(OperationsReportService::class);
    }

    private function filters(array $extra = []): ReportFilters
    {
        return ReportFilters::fromArray(['seller_id' => $this->seller->id] + $extra);
    }

    private function makeStatus(string $type, string $name): Status
    {
        return Status::firstOrCreate(['english_type' => $type, 'english_name' => $name], ['type' => $type, 'name' => $name]);
    }

    private function order(array $attributes = []): RegisteredOrder
    {
        return RegisteredOrder::factory()->create($attributes + [
            'seller_id' => $this->seller->id,
            'order_date' => today()->toDateString(),
            'validity_date' => null,
        ]);
    }

    private function backdate(string $class, int $id, int $days): void
    {
        $at = today()->subDays($days);
        DB::table('activity_log')->where('subject_type', $class)->where('subject_id', $id)->update(['created_at' => $at]);
        DB::table('status_histories')->where('statusable_type', $class)->where('statusable_id', $id)->update(['created_at' => $at]);
    }

    private function row(RegisteredOrder $order, array $extra = []): object
    {
        return $this->service()->query($this->filters($extra))->get()->firstWhere('id', $order->id);
    }

    private function closedOrder(array $attributes = []): RegisteredOrder
    {
        $order = $this->order($attributes);
        $shipment = Shipment::factory()->create(['registered_order_id' => $order->id]);
        Custom::factory()->create(['registered_order_id' => $order->id, 'shipment_id' => $shipment->id, 'clearance_status_id' => $this->completed->id]);

        return $order;
    }

    private function profileStatus(): Status
    {
        return $this->profileStatus ??= $this->makeStatus(BankProfile::TYPE_BANK_PROFILE, BankProfileStatus::Allocated->value);
    }

    private function fullOrder(): RegisteredOrder
    {
        $order = $this->order();
        RegisteredOrderItem::factory()->create(['registered_order_id' => $order->id]);
        BankProfile::factory()->create(['registered_order_id' => $order->id, 'status_id' => $this->profileStatus()->id]);
        Shipment::factory()->create(['registered_order_id' => $order->id]);
        Correspondence::factory()->forCorrespondable($order)->create();

        return $order;
    }

    private function card(string $key, ?ReportFilters $filters = null): array
    {
        return collect($this->service()->summary($filters ?? $this->filters()))->firstWhere('key', $key);
    }

    public function test_an_order_is_open_until_every_shipment_has_completed_customs(): void
    {
        $bare = $this->order();
        $closed = $this->closedOrder();
        $partial = $this->closedOrder();
        $other = Shipment::factory()->create(['registered_order_id' => $partial->id]);
        Custom::factory()->create(['registered_order_id' => $partial->id, 'shipment_id' => $other->id, 'clearance_status_id' => $this->makeStatus(Custom::TYPE_CLEARANCE_STATUS, ClearanceStatus::Declared->value)->id]);

        $ids = $this->service()->query($this->filters())->pluck('registered_orders.id')->all();

        $this->assertContains($bare->id, $ids);
        $this->assertContains($partial->id, $ids);
        $this->assertNotContains($closed->id, $ids);
    }

    public function test_an_order_stays_open_while_a_second_customs_file_of_a_shipment_is_unfinished(): void
    {
        $order = $this->closedOrder();
        $shipment = $order->shipments()->first();
        Custom::factory()->create(['registered_order_id' => $order->id, 'shipment_id' => $shipment->id, 'clearance_status_id' => $this->makeStatus(Custom::TYPE_CLEARANCE_STATUS, ClearanceStatus::Declared->value)->id]);

        $ids = $this->service()->query($this->filters())->pluck('registered_orders.id')->all();

        $this->assertContains($order->id, $ids);
    }

    public function test_the_stage_follows_the_most_advanced_related_record(): void
    {
        $registration = $this->order();
        $bank = $this->order();
        BankProfile::factory()->create(['registered_order_id' => $bank->id]);
        $paid = $this->order();
        Payment::factory()->forTargetable($paid)->create();
        $paidViaOrder = $this->order();
        $single = PurchaseOrder::factory()->create();
        $paidViaOrder->purchaseOrders()->attach($single->id);
        Payment::factory()->forTargetable($single)->create();
        $shared = $this->order();
        $sharedOrder = PurchaseOrder::factory()->create();
        $shared->purchaseOrders()->attach($sharedOrder->id);
        $this->order()->purchaseOrders()->attach($sharedOrder->id);
        Payment::factory()->forTargetable($sharedOrder)->create();
        $shipped = $this->order();
        Shipment::factory()->create(['registered_order_id' => $shipped->id]);
        $customs = $this->order();
        Custom::factory()->create(['registered_order_id' => $customs->id, 'clearance_status_id' => $this->makeStatus(Custom::TYPE_CLEARANCE_STATUS, ClearanceStatus::Declared->value)->id]);

        $stages = $this->service()->query($this->filters())->get()->pluck('stage', 'id');

        $this->assertSame('registration', $stages[$registration->id]);
        $this->assertSame('bank_profile', $stages[$bank->id]);
        $this->assertSame('payment', $stages[$paid->id]);
        $this->assertSame('payment', $stages[$paidViaOrder->id]);
        $this->assertSame('registration', $stages[$shared->id]);
        $this->assertSame('shipment', $stages[$shipped->id]);
        $this->assertSame('customs', $stages[$customs->id]);
    }

    public function test_last_activity_comes_from_the_order_and_its_children_and_falls_back_to_updated_at(): void
    {
        $order = $this->order();
        $this->backdate(RegisteredOrder::class, $order->id, 20);
        DB::table('registered_orders')->where('id', $order->id)->update(['updated_at' => today()->subDays(15)]);
        $this->assertSame(20, (int) $this->row($order)->idle_days);

        $shipment = Shipment::factory()->create(['registered_order_id' => $order->id]);
        $this->backdate(Shipment::class, $shipment->id, 4);
        $this->assertSame(4, (int) $this->row($order)->idle_days);
        $this->assertEquals(0, $this->row($order)->idle_from_updated_at);

        DB::table('activity_log')->where('log_name', 'audit')->whereIn('subject_type', [RegisteredOrder::class, Shipment::class])->whereIn('subject_id', [$order->id, $shipment->id])->delete();
        DB::table('status_histories')->whereIn('statusable_type', [RegisteredOrder::class, Shipment::class])->whereIn('statusable_id', [$order->id, $shipment->id])->delete();

        $row = $this->row($order);
        $this->assertSame(15, (int) $row->idle_days);
        $this->assertEquals(1, $row->idle_from_updated_at);
    }

    public function test_only_the_audit_log_counts_as_activity(): void
    {
        $order = $this->order();
        $this->backdate(RegisteredOrder::class, $order->id, 20);
        DB::table('status_histories')->where('statusable_type', RegisteredOrder::class)->where('statusable_id', $order->id)->delete();
        DB::table('activity_log')->insert([
            'log_name' => 'calendar', 'description' => 'x', 'subject_type' => RegisteredOrder::class, 'subject_id' => $order->id,
            'event' => 'x', 'properties' => '[]', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(20, (int) $this->row($order)->idle_days);
    }

    private function actingAsUserViewing(array $permissions): User
    {
        $actor = User::factory()->create();
        $role = Role::create(['name' => 'last_operation_role_'.uniqid(), 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $actor->assignRole($role);
        $this->actingAs($actor);

        return $actor;
    }

    public function test_the_last_operation_is_hidden_for_a_viewer_without_the_module_view_permission(): void
    {
        $this->actingAsUserViewing([]);
        $order = $this->order();
        $to = $this->makeStatus(RegisteredOrder::TYPE_REGISTERED_ORDER, 'Operations Hidden '.uniqid());
        $order->update(['status_id' => $to->id]);

        $this->assertSame('-', $this->row($order)->last_event['text']);
    }

    public function test_the_last_operation_names_the_status_change_and_who_made_it(): void
    {
        $actor = $this->actingAsUserViewing(['registered_order.view']);
        $order = $this->order();
        $to = $this->makeStatus(RegisteredOrder::TYPE_REGISTERED_ORDER, 'Operations Test '.uniqid());
        $order->update(['status_id' => $to->id]);
        DB::table('status_histories')->where('statusable_type', RegisteredOrder::class)->where('statusable_id', $order->id)->where('to_status_id', $to->id)->update(['created_at' => now()->addMinute()]);
        DB::table('activity_log')->where('subject_type', RegisteredOrder::class)->where('subject_id', $order->id)->update(['created_at' => now()->subHour()]);

        $event = $this->row($order)->last_event;

        $this->assertStringContainsString('→', $event['text']);
        $this->assertStringContainsString($to->localized_name, $event['text']);
        $this->assertSame($actor->name, $event['by']);
    }

    public function test_the_fx_status_is_the_most_advanced_bank_profile_stage(): void
    {
        $order = $this->order();
        foreach ([BankProfileStatus::AwaitingSupply, BankProfileStatus::Allocated, BankProfileStatus::ReadyForAllocation] as $case) {
            BankProfile::factory()->create(['registered_order_id' => $order->id, 'status_id' => $this->makeStatus(BankProfile::TYPE_BANK_PROFILE, $case->value)->id]);
        }

        $this->assertSame(BankProfileStatus::Allocated->value, $this->row($order)->fx_status->english_name);
    }

    public function test_latest_shipment_customs_and_correspondence_are_attached(): void
    {
        $order = $this->order();
        Shipment::factory()->create(['registered_order_id' => $order->id]);
        $latest = Shipment::factory()->create(['registered_order_id' => $order->id]);
        $declared = $this->makeStatus(Custom::TYPE_CLEARANCE_STATUS, ClearanceStatus::Declared->value);
        Custom::factory()->create(['registered_order_id' => $order->id, 'shipment_id' => $latest->id, 'clearance_status_id' => $declared->id]);
        $mail = Correspondence::factory()->forCorrespondable($order)->create(['subject' => 'Latest mail '.uniqid()]);

        $row = $this->row($order);

        $this->assertSame($latest->id, $row->latest_shipment->id);
        $this->assertSame($declared->id, $row->clearance_status->id);
        $this->assertSame($mail->subject, $row->latest_mail->subject);
    }

    public function test_the_four_cards_count_stage_idle_validity_and_priority_correspondence(): void
    {
        $idle = $this->order(['validity_date' => today()->addDays(10)->toDateString()]);
        $this->backdate(RegisteredOrder::class, $idle->id, 20);
        $expired = $this->order(['validity_date' => today()->subDay()->toDateString()]);
        Correspondence::factory()->forCorrespondable($expired)->create(['priority' => 'high']);
        Correspondence::factory()->forCorrespondable($expired)->create(['priority' => 'urgent']);
        Correspondence::factory()->forCorrespondable($expired)->create(['priority' => 'low']);
        $this->closedOrder(['validity_date' => today()->subDay()->toDateString()]);

        $cards = collect($this->service()->summary($this->filters()));
        $values = fn (string $key) => collect($cards->firstWhere('key', $key)['lines'])->pluck('value')->all();

        $this->assertCount(4, $cards);
        $this->assertSame('2', $values('stages')[0]);
        $this->assertSame(['1', '1'], $values('idle'));
        $this->assertSame(['1', '1', '0'], $values('expiring'));
        $this->assertSame(['1', '1'], $values('correspondence'));
        $this->assertSame('1', collect($cards->firstWhere('key', 'stages')['lines'])->last()['value']);
    }

    public function test_validity_ignores_orders_with_a_purchase_order_and_today_is_not_expired(): void
    {
        $fulfilled = $this->order(['validity_date' => today()->subDay()->toDateString()]);
        $fulfilled->purchaseOrders()->attach(PurchaseOrder::factory()->create()->id);
        $today = $this->order(['validity_date' => today()->toDateString()]);
        $open = $this->order(['validity_date' => today()->subDay()->toDateString()]);

        $expired = $this->offenders('registered_order.validity_expired');
        $expiring = $this->offenders('registered_order.validity_expiring');

        $this->assertContains($open->id, $expired);
        $this->assertNotContains($fulfilled->id, $expired);
        $this->assertNotContains($today->id, $expired);
        $this->assertContains($today->id, $expiring);
        $this->assertEqualsCanonicalizing($expired, array_intersect($this->quickIds('expired'), $expired));
        $this->assertNotContains($fulfilled->id, $this->quickIds('expired'));
    }

    public function test_the_stage_card_and_chart_agree_with_the_stage_counts(): void
    {
        $this->order();
        $this->order();
        $bank = $this->order();
        BankProfile::factory()->create(['registered_order_id' => $bank->id]);

        $chart = $this->service()->chartStages($this->filters());
        $lines = collect($this->card('stages')['lines'])->pluck('value')->all();

        $this->assertCount(count(OperationsReportService::STAGES), $chart['labels']);
        $this->assertSame([2, 1, 0, 0, 0], $chart['counts']);
        $this->assertSame(['3', '2', '1', '0', '0', '0', '0'], $lines);
    }

    public function test_the_summary_and_charts_are_cached_yet_follow_a_saved_order_and_never_share_labels_between_locales(): void
    {
        $this->order();
        $first = $this->service()->summary($this->filters());
        $stages = $this->service()->chartStages($this->filters());
        $idle = $this->service()->chartIdleByStage($this->filters());

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame($first, $this->service()->summary($this->filters()));
        $this->assertSame($stages, $this->service()->chartStages($this->filters()));
        $this->assertSame($idle, $this->service()->chartIdleByStage($this->filters()));
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();

        $this->order();

        $this->assertSame('2', $this->service()->summary($this->filters())[0]['lines'][0]['value']);
        $this->assertSame(2, array_sum($this->service()->chartStages($this->filters())['counts']));
        $this->assertSame(2, array_sum($this->service()->chartIdleByStage($this->filters())['active']) + array_sum($this->service()->chartIdleByStage($this->filters())['idle']));

        app()->setLocale('fa');
        $this->assertNotSame($first[0]['label'], $this->service()->summary($this->filters())[0]['label']);
        $this->assertNotSame($stages['labels'], $this->service()->chartStages($this->filters())['labels']);
    }

    public function test_the_idle_by_stage_chart_splits_each_stage_and_reconciles_with_the_stage_chart_and_the_idle_card(): void
    {
        $idle = $this->order();
        $this->backdate(RegisteredOrder::class, $idle->id, 30);
        $this->order();
        $withBank = $this->order();
        BankProfile::factory()->create(['registered_order_id' => $withBank->id]);
        $this->backdate(RegisteredOrder::class, $withBank->id, 30);
        $this->backdate(BankProfile::class, BankProfile::query()->where('registered_order_id', $withBank->id)->value('id'), 30);

        $chart = $this->service()->chartIdleByStage($this->filters());
        $stages = $this->service()->chartStages($this->filters());
        $idleLine = (int) collect($this->card('idle')['lines'])->first()['value'];

        $this->assertSame(array_map(fn (int $active, int $stuck): int => $active + $stuck, $chart['active'], $chart['idle']), $stages['counts']);
        $this->assertSame($idleLine, array_sum($chart['idle']));
        $this->assertSame([1, 0, 0, 0, 0], $chart['active']);
        $this->assertSame([1, 1, 0, 0, 0], $chart['idle']);
    }

    public function test_every_shared_filter_narrows_the_result(): void
    {
        $product = Product::factory()->create();
        $currency = Currency::factory()->create();
        $buyer = Company::factory()->create();
        $status = $this->makeStatus(RegisteredOrder::TYPE_REGISTERED_ORDER, 'Filter Test '.uniqid());
        $a = $this->order(['currency_id' => $currency->id, 'buyer_id' => $buyer->id, 'status_id' => $status->id, 'order_date' => '2026-01-10']);
        RegisteredOrderItem::factory()->create(['registered_order_id' => $a->id, 'product_id' => $product->id]);
        $b = $this->order(['order_date' => '2026-03-10']);
        $ids = fn (array $extra) => $this->service()->query($this->filters($extra))->pluck('registered_orders.id')->all();

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $ids([]));
        $this->assertSame([$a->id], $ids(['currency_id' => $currency->id]));
        $this->assertSame([$a->id], $ids(['buyer_id' => $buyer->id]));
        $this->assertSame([$a->id], $ids(['status_id' => [$status->id]]));
        $this->assertSame([$a->id], $ids(['product_id' => $product->id]));
        $this->assertSame([$a->id], $ids(['category_id' => $product->category_id]));
        $this->assertSame([$b->id], $ids(['registered_order_id' => $b->id]));
        $this->assertSame([$b->id], $ids(['date_from' => '2026-02-01']));
        $this->assertSame([$a->id], $ids(['date_until' => '2026-02-01']));
    }

    public function test_the_operations_filters_are_sanitised_and_accept_whole_floats_from_number_inputs(): void
    {
        $this->assertSame(3, ReportFilters::fromArray(['idle_days' => 3.0])->idleDays);
        $this->assertSame(ReportFilters::IDLE_DAYS, ReportFilters::fromArray(['idle_days' => 'abc'])->idleDays);
        $this->assertSame(ReportFilters::DEFAULT_ACTIVITY_WINDOW, ReportFilters::fromArray(['activity_window' => '1y'])->activityWindow);
        $this->assertSame('30d', ReportFilters::fromArray(['activity_window' => '30d'])->activityWindow);
        $this->assertNull(ReportFilters::fromArray(['module' => 'a b; drop'])->module);
        $this->assertSame(['activity_window' => '7d', 'module' => null, 'idle_days' => 7], array_intersect_key(ReportFilters::fromArray([])->toArray(), array_flip(['activity_window', 'module', 'idle_days'])));
    }

    public function test_trashed_orders_never_appear(): void
    {
        $order = $this->order();
        $order->delete();

        $this->assertNotContains($order->id, $this->service()->query($this->filters())->pluck('registered_orders.id')->all());
    }

    public function test_export_rows_use_exactly_the_exported_labels(): void
    {
        $this->order();

        $row = collect($this->service()->rows($this->filters()))->first();

        $this->assertSame(array_keys($this->service()->exportLabels()), array_keys($row));
    }

    public function test_the_row_query_count_is_constant(): void
    {
        $this->fullOrder();
        $this->service()->query($this->filters())->get();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->service()->query($this->filters())->get();
        $one = count(DB::getQueryLog());

        foreach (range(1, 5) as $i) {
            $this->fullOrder();
        }
        DB::flushQueryLog();
        $this->service()->query($this->filters())->get();
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($one, $many);
    }

    public function test_the_registry_holds_the_operations_checks_with_the_planned_severities(): void
    {
        $checks = app(CheckRegistry::class)->forReport('operations')->keyBy('key');

        $this->assertEqualsCanonicalizing(collect(self::keys())->flatten()->all(), $checks->keys()->all());
        $this->assertSame('warning', $checks['registered_order.idle_over_threshold']->severity);
        $this->assertSame('error', $checks['registered_order.idle_over_14']->severity);
        $this->assertSame('warning', $checks['registered_order.validity_expiring']->severity);
        $this->assertSame('error', $checks['registered_order.validity_expired']->severity);
        $this->assertSame('error', $checks['registered_order.no_items']->severity);
        $this->assertSame('warning', $checks['registered_order.no_bank_profile_after_n_days']->severity);
        $this->assertSame('warning', $checks['registered_order.missing_validity_date']->severity);
        $this->assertSame('error', $checks['registered_order.missing_party']->severity);
        $this->assertSame('error', $checks['registered_order.missing_currency']->severity);
        $this->assertSame('warning', $checks['registered_order.in_customs_without_payment_or_bank_profile']->severity);
        $this->assertSame('warning', $checks['shipment.missing_tracking_status']->severity);
        $this->assertSame('warning', $checks['correspondence.open_high_priority']->severity);
    }

    public function test_every_fix_points_at_a_real_resource_tab_key_and_field(): void
    {
        $resources = [
            RegisteredOrder::class => RegisteredOrderResource::class,
            Shipment::class => ShipmentResource::class,
            Custom::class => CustomResource::class,
            Correspondence::class => CorrespondenceResource::class,
        ];

        foreach (app(CheckRegistry::class)->forReport('operations') as $check) {
            $this->assertSame($resources[$check->model], $check->fix['resource']);
            $this->assertNotSame($check->fix['tab'], __($check->fix['tab']));
            $this->assertTrue(in_array($check->fix['field'], (new $check->model)->getFillable(), true) || method_exists($check->model, $check->fix['field']), $check->key);
        }
    }

    public function test_every_message_and_expected_text_is_translated_in_all_locales(): void
    {
        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);

            foreach (app(CheckRegistry::class)->forReport('operations') as $check) {
                $this->assertNotSame($check->messageKey(), __($check->messageKey()));
                $this->assertNotSame($check->expectedKey(), __($check->expectedKey()));
            }
        }
    }

    private function check(string $key): Check
    {
        return app(CheckRegistry::class)->forReport('operations')->firstWhere('key', $key);
    }

    private function offenders(string $key, ?ReportFilters $filters = null): array
    {
        $check = $this->check($key);

        return ($check->query)($check->model::query(), $filters ?? $this->filters())->get()->modelKeys();
    }

    private function fixture(string $key, bool $offending): int
    {
        $order = match ($key) {
            'registered_order.validity_expiring' => $this->order(['validity_date' => today()->addDays($offending ? 10 : 60)->toDateString()]),
            'registered_order.validity_expired' => $this->order(['validity_date' => today()->addDays($offending ? -1 : 1)->toDateString()]),
            'registered_order.no_bank_profile_after_n_days' => $this->order(['order_date' => today()->subDays(40)->toDateString()]),
            'registered_order.missing_validity_date' => $this->order($offending ? [] : ['validity_date' => today()->addDays(60)->toDateString()]),
            'registered_order.missing_party' => $this->orderWithBuyer($offending),
            'registered_order.missing_currency' => $this->orderWithCurrency($offending),
            default => $this->order(),
        };

        return match ($key) {
            'registered_order.idle_over_threshold' => $this->idle($order, $offending ? 10 : 1),
            'registered_order.idle_over_14' => $this->idle($order, $offending ? 20 : 10),
            'registered_order.no_items' => $this->withItem($order, ! $offending),
            'registered_order.no_bank_profile_after_n_days' => $this->withProfile($order, ! $offending),
            'registered_order.in_customs_without_payment_or_bank_profile' => $this->shippedOrder($order, $offending),
            'shipment.missing_tracking_status' => Shipment::factory()->create(['registered_order_id' => $order->id, 'etd' => today(), 'shipment_status_id' => $offending ? null : $this->makeStatus(Shipment::TYPE_TRACKING_STATUS, 'Any')->id])->id,
            'correspondence.open_high_priority' => Correspondence::factory()->forCorrespondable($order)->create(['priority' => $offending ? 'urgent' : 'normal'])->id,
            default => $order->id,
        };
    }

    private function orderWithBuyer(bool $gone): RegisteredOrder
    {
        $buyer = Company::factory()->create();
        $order = $this->order(['buyer_id' => $buyer->id]);
        $gone && $buyer->delete();

        return $order;
    }

    private function orderWithCurrency(bool $gone): RegisteredOrder
    {
        $currency = Currency::factory()->create();
        $order = $this->order(['currency_id' => $currency->id]);
        $gone && $currency->delete();

        return $order;
    }

    private function shippedOrder(RegisteredOrder $order, bool $bare): int
    {
        Shipment::factory()->create(['registered_order_id' => $order->id]);
        $bare || BankProfile::factory()->create(['registered_order_id' => $order->id]);

        return $order->id;
    }

    private function idle(RegisteredOrder $order, int $days): int
    {
        $this->backdate(RegisteredOrder::class, $order->id, $days);

        return $order->id;
    }

    private function withItem(RegisteredOrder $order, bool $has): int
    {
        $has && RegisteredOrderItem::factory()->create(['registered_order_id' => $order->id]);

        return $order->id;
    }

    private function withProfile(RegisteredOrder $order, bool $has): int
    {
        $has && BankProfile::factory()->create(['registered_order_id' => $order->id]);

        return $order->id;
    }

    #[DataProvider('keys')]
    public function test_an_offender_is_returned_and_a_compliant_record_is_not(string $key): void
    {
        $offender = $this->fixture($key, true);
        $compliant = $this->fixture($key, false);

        $ids = $this->offenders($key);

        $this->assertContains($offender, $ids);
        $this->assertNotContains($compliant, $ids);
    }

    #[DataProvider('keys')]
    public function test_the_seller_filter_narrows_the_result(string $key): void
    {
        $mine = $this->fixture($key, true);
        $this->seller = Company::factory()->create();
        $theirs = $this->fixture($key, true);

        $this->assertSame([$theirs], $this->offenders($key));
        $this->assertNotContains($mine, $this->offenders($key));
    }

    #[DataProvider('keys')]
    public function test_offending_and_expected_texts_are_non_empty_strings(string $key): void
    {
        $this->fixture($key, true);
        $check = $this->check($key);
        $record = ($check->query)($check->model::query(), $this->filters())->first();

        $this->assertIsString(($check->offending)($record));
        $this->assertNotSame('', ($check->expected)($record));
    }

    #[DataProvider('keys')]
    public function test_closed_orders_are_never_checked(string $key): void
    {
        $order = $this->closedOrder(['validity_date' => today()->subDay()->toDateString(), 'order_date' => today()->subDays(60)->toDateString()]);
        $this->backdate(RegisteredOrder::class, $order->id, 30);
        $shipment = $order->shipments()->first();
        $mail = Correspondence::factory()->forCorrespondable($order)->create(['priority' => 'urgent']);
        $related = [
            RegisteredOrder::class => [$order->id],
            Shipment::class => [$shipment->id],
            Correspondence::class => [$mail->id],
        ];

        $found = array_intersect($this->offenders($key), $related[$this->check($key)->model]);

        $this->assertEmpty($found);
    }

    public function test_the_idle_threshold_follows_the_filter_and_the_error_check_does_not(): void
    {
        $order = $this->order();
        $this->backdate(RegisteredOrder::class, $order->id, 10);

        $this->assertContains($order->id, $this->offenders('registered_order.idle_over_threshold'));
        $this->assertNotContains($order->id, $this->offenders('registered_order.idle_over_threshold', $this->filters(['idle_days' => 30])));
        $this->assertNotContains($order->id, $this->offenders('registered_order.idle_over_14'));
    }

    public function test_an_order_without_activity_rows_is_checked_from_updated_at_and_says_so(): void
    {
        $order = $this->order();
        DB::table('activity_log')->where('subject_type', RegisteredOrder::class)->where('subject_id', $order->id)->delete();
        DB::table('status_histories')->where('statusable_type', RegisteredOrder::class)->where('statusable_id', $order->id)->delete();
        DB::table('registered_orders')->where('id', $order->id)->update(['updated_at' => today()->subDays(9)]);

        $row = app(CheckRegistry::class)->evaluate($this->check('registered_order.idle_over_threshold'), $this->filters())->firstWhere('id', $order->id);

        $this->assertStringContainsString('9', $row['offending']);
        $this->assertStringContainsString(__('resources/report/strings.attention.no_history_note'), $row['offending']);
    }

    public function test_only_high_and_urgent_correspondence_on_the_file_or_its_shipments_and_customs_is_listed(): void
    {
        $order = $this->order();
        $shipment = Shipment::factory()->create(['registered_order_id' => $order->id]);
        $own = Correspondence::factory()->forCorrespondable($order)->create(['priority' => 'high']);
        $viaShipment = Correspondence::factory()->forCorrespondable($shipment)->create(['priority' => 'urgent']);
        $low = Correspondence::factory()->forCorrespondable($order)->create(['priority' => 'low']);
        $elsewhere = Correspondence::factory()->forCorrespondable($this->order(['seller_id' => Company::factory()->create()->id]))->create(['priority' => 'high']);

        $ids = $this->offenders('correspondence.open_high_priority');

        $this->assertEqualsCanonicalizing([$own->id, $viaShipment->id], $ids);
        $this->assertNotContains($low->id, $ids);
        $this->assertNotContains($elsewhere->id, $ids);
    }

    public function test_approved_and_archived_correspondence_is_closed_and_never_counted_as_open(): void
    {
        $order = $this->order();
        $status = fn (string $name): Status => Status::factory()->create(['type' => Correspondence::TYPE_CORRESPONDENCE_STATUS, 'english_type' => Correspondence::TYPE_CORRESPONDENCE_STATUS, 'name' => $name, 'english_name' => $name]);
        $mail = fn (string $name): Correspondence => Correspondence::factory()->forCorrespondable($order)->create(['priority' => 'urgent', 'status_id' => $status($name)->id]);
        $submitted = $mail('Submitted');
        $draft = $mail('Draft');
        $approved = $mail('Approved');
        $archived = $mail('Archived');

        $ids = $this->offenders('correspondence.open_high_priority');

        $this->assertEqualsCanonicalizing([$submitted->id, $draft->id], $ids);
        $this->assertNotContains($approved->id, $ids);
        $this->assertNotContains($archived->id, $ids);
    }

    public function test_the_check_cache_is_cleared_when_an_order_is_saved(): void
    {
        $order = $this->order(['validity_date' => today()->subDay()->toDateString()]);
        $check = $this->check('registered_order.validity_expired');
        $registry = app(CheckRegistry::class);

        $this->assertTrue($registry->evaluate($check, $this->filters())->pluck('id')->contains($order->id));

        $order->update(['validity_date' => today()->addDays(90)->toDateString()]);

        $this->assertFalse($registry->evaluate($check, $this->filters())->pluck('id')->contains($order->id));
    }

    public function test_the_feed_lists_audit_and_status_changes_inside_the_window_for_viewable_models_only(): void
    {
        $actor = $this->viewer(['registered_order.view']);
        $this->actingAs($actor);
        $order = $this->order();
        $order->update(['status_id' => $this->makeStatus(RegisteredOrder::TYPE_REGISTERED_ORDER, 'Feed Test '.uniqid())->id, 'contract_no' => 'FEED-'.uniqid()]);
        $shipment = Shipment::factory()->create(['registered_order_id' => $order->id]);
        $old = $this->order();
        $this->backdate(RegisteredOrder::class, $old->id, 40);
        $feed = app(ActivityFeed::class);
        $mine = fn (ReportFilters $filters) => $feed->query($filters)->whereIn('subject_id', [$order->id, $old->id, $shipment->id])->get();

        $rows = $mine(ReportFilters::fromArray(['causer_id' => $actor->id]));

        $this->assertEqualsCanonicalizing(['a', 's'], $rows->pluck('kind')->unique()->all());
        $this->assertSame([RegisteredOrder::class], $rows->pluck('subject_type')->unique()->all());
        $this->assertNotContains($old->id, $rows->pluck('subject_id')->all());
        $this->assertNotContains($shipment->id, $rows->where('subject_type', Shipment::class)->pluck('subject_id')->all());
    }

    public function test_the_feed_window_causer_and_module_filters_narrow_the_rows(): void
    {
        $actor = $this->viewer(['registered_order.view', 'shipment.view']);
        $this->actingAs($actor);
        $order = $this->order();
        $shipment = Shipment::factory()->create(['registered_order_id' => $order->id]);
        $this->backdate(RegisteredOrder::class, $order->id, 10);
        $feed = app(ActivityFeed::class);
        $count = fn (array $filters) => $feed->query(ReportFilters::fromArray($filters))->whereIn('subject_id', [$order->id, $shipment->id])->get();

        $this->assertNotContains(RegisteredOrder::class, $count(['causer_id' => $actor->id])->pluck('subject_type')->all());
        $this->assertContains(RegisteredOrder::class, $count(['causer_id' => $actor->id, 'activity_window' => '30d'])->pluck('subject_type')->all());
        $this->assertSame([Shipment::class], $count(['causer_id' => $actor->id, 'activity_window' => '30d', 'module' => 'shipment'])->pluck('subject_type')->unique()->all());
        $this->assertCount(0, $count(['causer_id' => User::factory()->create()->id, 'activity_window' => '30d']));
    }

    public function test_feed_rows_resolve_file_module_change_and_system_actor(): void
    {
        $actor = $this->viewer(['registered_order.view', 'shipment.view']);
        $this->actingAs($actor);
        $order = $this->order();
        $order->update(['contract_no' => 'FEED-'.uniqid()]);
        $shipment = Shipment::factory()->create(['registered_order_id' => $order->id]);
        DB::table('activity_log')->where('subject_type', Shipment::class)->where('subject_id', $shipment->id)->update(['causer_id' => null, 'causer_type' => null]);
        DB::table('status_histories')->where('statusable_type', Shipment::class)->where('statusable_id', $shipment->id)->update(['user_id' => null]);
        $feed = app(ActivityFeed::class);

        $rows = collect($feed->rows($feed->query(ReportFilters::fromArray([]))->where(fn ($pair) => $pair->where(fn ($o) => $o->where('subject_type', RegisteredOrder::class)->where('subject_id', $order->id))->orWhere(fn ($s) => $s->where('subject_type', Shipment::class)->where('subject_id', $shipment->id)))->get()));

        $shipmentRow = $rows->firstWhere('module', \App\Services\PermissionLabeler::getEntityLabel(Shipment::class));
        $this->assertSame($order->ro_number, $shipmentRow['file']);
        $this->assertSame(__('resources/report/strings.operations.feed.system'), $shipmentRow['by']);
        $this->assertSame($actor->name, $rows->firstWhere('module', \App\Services\PermissionLabeler::getEntityLabel(RegisteredOrder::class))['by']);
        $this->assertStringContainsString('→', $rows->firstWhere('file', $order->ro_number)['change']);
    }

    private function quickMix(): void
    {
        $this->order();
        $idle = $this->order(['validity_date' => today()->addDays(10)->toDateString()]);
        $this->backdate(RegisteredOrder::class, $idle->id, 20);
        $this->order(['validity_date' => today()->subDay()->toDateString()]);
        $bank = $this->order(['validity_date' => today()->addDays(30)->toDateString()]);
        BankProfile::factory()->create(['registered_order_id' => $bank->id]);
        $this->backdate(RegisteredOrder::class, $bank->id, 9);
        $this->backdate(BankProfile::class, BankProfile::query()->where('registered_order_id', $bank->id)->value('id'), 9);
        Payment::factory()->forTargetable($this->order())->create();
        Shipment::factory()->create(['registered_order_id' => $this->order()->id]);
        Custom::factory()->create(['registered_order_id' => $this->order()->id, 'clearance_status_id' => $this->makeStatus(Custom::TYPE_CLEARANCE_STATUS, ClearanceStatus::Declared->value)->id]);
        $this->closedOrder(['validity_date' => today()->subDay()->toDateString()]);
    }

    private function quickIds(?string $quick): array
    {
        return $this->service()->query($this->filters(['quick' => $quick]))->pluck('registered_orders.id')->all();
    }

    public function test_every_summary_line_with_a_quick_key_filters_the_table_to_exactly_its_count(): void
    {
        $this->quickMix();
        $quickLines = collect($this->service()->summary($this->filters()))->flatMap(fn (array $card): array => $card['lines'])->filter(fn (array $line): bool => isset($line['quick']));

        $this->assertEqualsCanonicalizing(['all_open', 'stage_registration', 'stage_bank_profile', 'stage_payment', 'stage_shipment', 'stage_customs', 'idle_threshold', 'idle_over_14', 'expiring', 'expired', 'no_validity'], $quickLines->pluck('quick')->all());

        foreach ($quickLines as $line) {
            $this->assertSame((int) $line['value'], count($this->quickIds($line['quick'])), $line['quick']);
        }

        $this->assertSame(['7', '3', '1', '1', '1', '1', '1'], collect($this->card('stages')['lines'])->pluck('value')->all());
        $this->assertArrayNotHasKey('quick', collect($this->card('stages')['lines'])->last());
    }

    public function test_the_correspondence_lines_have_no_quick_key(): void
    {
        $this->quickMix();

        foreach ($this->card('correspondence')['lines'] as $line) {
            $this->assertArrayNotHasKey('quick', $line);
        }
    }

    public function test_an_unknown_or_missing_quick_key_leaves_the_table_unchanged(): void
    {
        $this->quickMix();
        $all = $this->quickIds(null);

        $this->assertNotEmpty($all);
        $this->assertEqualsCanonicalizing($all, $this->quickIds('nope_123'));
        $this->assertEqualsCanonicalizing($all, $this->quickIds('stage_unknown'));
        $this->assertEqualsCanonicalizing($all, $this->quickIds('all_open'));
    }

    public function test_the_idle_and_validity_filters_follow_the_threshold_and_dates(): void
    {
        $this->quickMix();

        $this->assertCount(2, $this->quickIds('expiring'));
        $this->assertCount(1, $this->quickIds('expired'));
        $this->assertCount(1, $this->quickIds('idle_over_14'));
        $this->assertCount(2, $this->quickIds('idle_threshold'));
    }

    public function test_quick_does_not_change_the_cards_charts_or_checks(): void
    {
        $this->quickMix();
        $plain = $this->filters();
        $clicked = $this->filters(['quick' => 'stage_payment']);
        $check = $this->check('registered_order.validity_expired');

        $this->assertSame($this->service()->summary($plain), $this->service()->summary($clicked));
        $this->assertSame($this->service()->chartStages($plain), $this->service()->chartStages($clicked));
        $this->assertSame($this->service()->chartIdleByStage($plain), $this->service()->chartIdleByStage($clicked));
        $this->assertSame(app(CheckRegistry::class)->evaluate($check, $plain)->pluck('id')->all(), app(CheckRegistry::class)->evaluate($check, $clicked)->pluck('id')->all());
    }

    public function test_the_export_rows_honour_the_quick_filter(): void
    {
        $this->quickMix();

        $this->assertCount(1, collect($this->service()->rows($this->filters(['quick' => 'expired']))));
        $this->assertCount(count($this->quickIds(null)), collect($this->service()->rows($this->filters())));
    }

    private function idsFor(array $extra, bool $sellerScoped = true): array
    {
        $filters = $sellerScoped ? $this->filters($extra) : ReportFilters::fromArray($extra);

        return $this->service()->query($filters)->pluck('registered_orders.id')->all();
    }

    public function test_the_threshold_filters_move_the_card_line_the_rows_the_check_and_the_label_together(): void
    {
        $order = $this->order(['validity_date' => today()->addDays(20)->toDateString(), 'order_date' => today()->subDays(20)->toDateString()]);
        $this->backdate(RegisteredOrder::class, $order->id, 20);
        $scenarios = [
            'defaults' => [[], 1, 1, 0],
            'moved' => [['th_idle_error' => 25, 'th_validity' => 10, 'th_supply' => 15], 0, 0, 1],
        ];

        foreach ($scenarios as $name => [$extra, $idleError, $expiring, $supply]) {
            $filters = $this->filters($extra);
            $idle = $this->card('idle', $filters)['lines'][1];
            $validity = $this->card('expiring', $filters)['lines'][0];

            $this->assertSame((string) $idleError, $idle['value'], $name);
            $this->assertSame((string) $expiring, $validity['value'], $name);
            $this->assertSame($idleError, count($this->idsFor($extra + ['quick' => 'idle_over_14'])), $name);
            $this->assertSame($expiring, count($this->idsFor($extra + ['quick' => 'expiring'])), $name);
            $this->assertSame($idleError, count($this->offenders('registered_order.idle_over_14', $filters)), $name);
            $this->assertSame($expiring, count($this->offenders('registered_order.validity_expiring', $filters)), $name);
            $this->assertSame($supply, count($this->offenders('registered_order.no_bank_profile_after_n_days', $filters)), $name);
            $this->assertSame(__('resources/report/strings.operations.lines.idle_critical', ['days' => $filters->threshold('idle_error')]), $idle['label'], $name);
            $this->assertSame(__('resources/report/strings.operations.lines.expiring', ['days' => $filters->threshold('validity')]), $validity['label'], $name);
        }
    }

    public function test_the_chart_colour_and_check_texts_resolve_the_thresholds_from_the_filters(): void
    {
        $order = $this->order(['order_date' => today()->subDays(40)->toDateString(), 'validity_date' => today()->addDays(5)->toDateString()]);
        $this->backdate(RegisteredOrder::class, $order->id, 20);
        $filters = $this->filters(['idle_days' => 3, 'th_idle_error' => 18, 'th_validity' => 8, 'th_supply' => 35]);
        $expected = fn (string $key): string => app(CheckRegistry::class)->evaluate($this->check($key), $filters)->firstWhere('id', $order->id)['expected'];

        $this->assertStringContainsString('3', $expected('registered_order.idle_over_threshold'));
        $this->assertStringContainsString('18', $expected('registered_order.idle_over_14'));
        $this->assertStringContainsString('8', $expected('registered_order.validity_expiring'));
        $this->assertStringContainsString('35', $expected('registered_order.no_bank_profile_after_n_days'));
        $this->assertSame([1, 0, 0, 0, 0], array_map('intval', $this->service()->chartIdleByStage($filters)['idle']));
        $this->assertSame([0, 0, 0, 0, 0], array_map('intval', $this->service()->chartIdleByStage($this->filters(['idle_days' => 30]))['idle']));
    }

    public function test_closed_orders_follow_the_cleared_stage_and_the_toggle_shows_them_with_a_hidden_count(): void
    {
        $declared = $this->makeStatus(Custom::TYPE_CLEARANCE_STATUS, ClearanceStatus::Declared->value);
        $open = $this->order();
        $closed = $this->closedOrder();
        $viaDeclared = $this->order();
        $shipment = Shipment::factory()->create(['registered_order_id' => $viaDeclared->id]);
        Custom::factory()->create(['registered_order_id' => $viaDeclared->id, 'shipment_id' => $shipment->id, 'clearance_status_id' => $declared->id]);
        $hidden = fn (array $extra): ?string => collect($this->card('stages', $this->filters($extra))['lines'])->firstWhere('label', __('resources/report/strings.operations.lines.closed_hidden'))['value'] ?? null;

        $this->assertEqualsCanonicalizing([$open->id, $viaDeclared->id], $this->idsFor([]));
        $this->assertSame('1', $hidden([]));

        $this->assertSame([$open->id], $this->idsFor(['cleared_from_stage' => $declared->id]));
        $this->assertSame('2', $hidden(['cleared_from_stage' => $declared->id]));

        $this->assertEqualsCanonicalizing([$open->id, $closed->id, $viaDeclared->id], $this->idsFor(['show_closed' => true]));
        $this->assertNull($hidden(['show_closed' => true]));
        $this->assertSame('3', $this->card('stages', $this->filters(['show_closed' => true]))['lines'][0]['value']);
    }

    public function test_the_checks_and_other_cards_follow_the_closed_toggle(): void
    {
        $closed = $this->closedOrder();
        $filters = $this->filters(['show_closed' => true]);

        $this->assertNotContains($closed->id, $this->offenders('registered_order.no_items'));
        $this->assertContains($closed->id, $this->offenders('registered_order.no_items', $filters));
        $this->assertSame(1, $this->service()->chartStages($filters)['counts'][4]);
        $this->assertSame(0, array_sum($this->service()->chartStages($this->filters())['counts']));
    }

    public function test_a_cleared_stage_that_is_not_a_clearance_status_falls_back_to_completed(): void
    {
        $order = $this->closedOrder();

        $this->assertNotContains($order->id, $this->idsFor(['cleared_from_stage' => $this->profileStatus()->id]));
    }

    public function test_the_stage_filter_narrows_rows_cards_charts_and_checks_together(): void
    {
        $registration = $this->order();
        $bank = $this->order();
        BankProfile::factory()->create(['registered_order_id' => $bank->id]);
        $shipped = $this->order();
        Shipment::factory()->create(['registered_order_id' => $shipped->id]);

        $this->assertSame([$bank->id], $this->idsFor(['stage' => 'bank_profile']));
        $this->assertSame('1', $this->card('stages', $this->filters(['stage' => 'bank_profile']))['lines'][0]['value']);
        $this->assertSame([0, 1, 0, 0, 0], $this->service()->chartStages($this->filters(['stage' => 'bank_profile']))['counts']);
        $this->assertSame([$bank->id], $this->offenders('registered_order.no_items', $this->filters(['stage' => 'bank_profile'])));
        $this->assertCount(3, $this->idsFor(['stage' => 'nonsense']));
        $this->assertEqualsCanonicalizing([$registration->id, $bank->id, $shipped->id], $this->idsFor([]));
    }

    public function test_the_company_filter_matches_the_seller_or_the_buyer(): void
    {
        $company = Company::factory()->create();
        $asSeller = $this->order(['seller_id' => $company->id]);
        $asBuyer = $this->order(['buyer_id' => $company->id]);
        $this->order();

        $this->assertEqualsCanonicalizing([$asSeller->id, $asBuyer->id], $this->idsFor(['company_id' => $company->id], false));
    }

    public function test_the_date_basis_moves_the_filter_and_undated_rows_stay_unless_switched_off(): void
    {
        $dated = $this->order(['order_date' => '2026-01-10', 'validity_date' => '2026-06-01']);
        $undated = $this->order(['order_date' => '2026-03-10', 'validity_date' => null]);

        $this->assertSame([$undated->id], $this->idsFor(['date_from' => '2026-02-01']));
        $this->assertSame([$dated->id], $this->idsFor(['date_until' => '2026-02-01']));
        $this->assertEqualsCanonicalizing([$dated->id, $undated->id], $this->idsFor(['date_from' => '2026-02-01', 'date_basis' => 'validity_date']));
        $this->assertSame([$dated->id], $this->idsFor(['date_from' => '2026-02-01', 'date_basis' => 'validity_date', 'include_undated' => false]));
        $this->assertSame([$undated->id], $this->idsFor(['date_until' => '2026-05-01', 'date_basis' => 'validity_date']));
        $this->assertSame([], $this->idsFor(['date_until' => '2026-05-01', 'date_basis' => 'validity_date', 'include_undated' => false]));
        $this->assertSame([], $this->idsFor(['date_until' => '2026-02-01', 'date_basis' => 'created_at']));
        $this->assertSame([$undated->id], $this->idsFor(['date_from' => '2026-02-01', 'date_basis' => 'not_a_basis']));
    }

    public function test_the_date_filter_reaches_cards_and_checks_with_the_same_basis(): void
    {
        $dated = $this->order(['validity_date' => '2026-06-01']);
        $undated = $this->order();
        $filters = $this->filters(['date_from' => '2026-07-01', 'date_basis' => 'validity_date', 'include_undated' => false]);

        $this->assertSame('0', $this->card('stages', $filters)['lines'][0]['value']);
        $this->assertSame([], $this->offenders('registered_order.no_items', $filters));
        $this->assertSame([$undated->id], $this->offenders('registered_order.no_items', $this->filters(['date_from' => '2026-07-01', 'date_basis' => 'validity_date'])));
        $this->assertNotContains($dated->id, $this->offenders('registered_order.no_items', $this->filters(['date_from' => '2026-07-01', 'date_basis' => 'validity_date'])));
    }

    public function test_orders_without_a_validity_date_have_their_own_line_that_equals_the_rows_and_the_check(): void
    {
        $this->order();
        $this->order();
        $this->order(['validity_date' => today()->addDays(90)->toDateString()]);
        $line = collect($this->card('expiring')['lines'])->firstWhere('quick', 'no_validity');

        $this->assertSame('2', $line['value']);
        $this->assertCount(2, $this->quickIds('no_validity'));
        $this->assertCount(2, $this->offenders('registered_order.missing_validity_date'));
    }

    public function test_the_missing_party_check_names_the_party_and_the_currency_check_fires_for_a_deleted_currency(): void
    {
        $party = $this->orderWithBuyer(true);
        $currency = $this->orderWithCurrency(true);
        $registry = app(CheckRegistry::class);

        $this->assertSame(__('resources/registeredOrder/strings.form.buyer'), $registry->evaluate($this->check('registered_order.missing_party'), $this->filters())->firstWhere('id', $party->id)['offending']);
        $this->assertNotNull($registry->evaluate($this->check('registered_order.missing_currency'), $this->filters())->firstWhere('id', $currency->id));
        $this->assertNull($registry->evaluate($this->check('registered_order.missing_currency'), $this->filters())->firstWhere('id', $party->id));
    }

    public function test_the_customs_without_payment_check_counts_any_payment_or_bank_profile_as_covered(): void
    {
        $bare = $this->order();
        Shipment::factory()->create(['registered_order_id' => $bare->id]);
        $customsOnly = $this->order();
        Custom::factory()->create(['registered_order_id' => $customsOnly->id, 'clearance_status_id' => $this->makeStatus(Custom::TYPE_CLEARANCE_STATUS, ClearanceStatus::Declared->value)->id]);
        $paid = $this->order();
        Shipment::factory()->create(['registered_order_id' => $paid->id]);
        Payment::factory()->forTargetable($paid)->create();
        $viaOrder = $this->order();
        Shipment::factory()->create(['registered_order_id' => $viaOrder->id]);
        $single = PurchaseOrder::factory()->create();
        $viaOrder->purchaseOrders()->attach($single->id);
        Payment::factory()->forTargetable($single)->create();
        $early = $this->order();

        $ids = $this->offenders('registered_order.in_customs_without_payment_or_bank_profile');

        $this->assertEqualsCanonicalizing([$bare->id, $customsOnly->id], $ids);
        $this->assertNotContains($early->id, $ids);
    }

    private function viewer(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'operations_feed_'.uniqid(), 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $user->assignRole($role);

        return $user;
    }
}
