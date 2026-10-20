<?php

namespace Tests\Feature\Filament\Reports;

use App\Filament\Pages\Reports\Concerns\HasReportFilters;
use App\Filament\Pages\Reports\ReportPage;
use App\Jobs\ExportReport;
use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\Calendar\CalendarModules;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\ReportFilters;
use App\Services\SmartCacheManager;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Reports\StubAttentionWidget;
use Tests\Support\Reports\StubReportPage;
use Tests\TestCase;

class ReportPageBaseTest extends TestCase
{
    private const ZERO = 'bank_profile.requested_amount_zero';

    private const OVERDUE = 'bank_profile.payment_overdue';

    private RegisteredOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        Queue::fake();
        app()->setLocale('en');
        StubReportPage::register();
        CheckRegistry::invalidate();
        $this->resetCaches();
        $this->order = RegisteredOrder::factory()->create();
    }

    protected function tearDown(): void
    {
        CheckRegistry::invalidate();
        app()->setLocale('en');
        DB::rollBack();
        $this->resetCaches();
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
        ReportStages::flush();
        SmartCacheManager::invalidate('Status');
    }

    private function actingAsUserWith(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'report_role_'.uniqid(), 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function allowed(): User
    {
        return $this->actingAsUserWith(['report.view_fx_supply', 'bank_profile.view']);
    }

    private function zero(?RegisteredOrder $order = null): BankProfile
    {
        return BankProfile::factory()->create([
            'registered_order_id' => ($order ?? $this->order)->id,
            'requested_amount' => 0,
            'payment_due_date' => null,
            'creation_date' => '2026-01-01',
            'allocation_date' => null,
            'purchase_date' => null,
        ]);
    }

    private function overdue(): BankProfile
    {
        $status = Status::factory()->create([
            'type' => BankProfile::TYPE_BANK_PROFILE,
            'english_type' => BankProfile::TYPE_BANK_PROFILE,
            'name' => 'Awaiting Supply',
            'english_name' => 'Awaiting Supply',
        ]);

        return BankProfile::factory()->create([
            'registered_order_id' => $this->order->id,
            'status_id' => $status->id,
            'requested_amount' => 1000,
            'payment_due_date' => today()->subDays(3)->toDateString(),
        ]);
    }

    private function widget(?RegisteredOrder $order = null)
    {
        return Livewire::test(StubAttentionWidget::class, ['pageFilters' => ['registered_order_id' => ($order ?? $this->order)->id]]);
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

    private function subject(): string
    {
        return get_class(new class
        {
            use HasReportFilters;
        });
    }

    private function fieldOptions(string $method, ...$args): array
    {
        return $this->subject()::$method(...$args)->getOptions();
    }

    private function tag(): string
    {
        return strtoupper(substr(md5(uniqid('', true)), 0, 10));
    }

    public static function locales(): array
    {
        return ['fa' => ['fa'], 'en' => ['en']];
    }

    private function column(string $locale): string
    {
        return $locale === 'fa' ? 'name' : 'english_name';
    }

    private function names(string $tag): array
    {
        return ['name' => "FA-$tag", 'english_name' => "EN-$tag"];
    }

    private function assertListsActiveNotInactive(string $locale, string $method, $active, $inactive): void
    {
        app()->setLocale($locale);
        $options = $this->fieldOptions($method);
        $expected = $locale === 'fa' ? $active->name : $active->english_name;

        $this->assertSame($expected, $options[$active->id] ?? null);
        $this->assertArrayNotHasKey($inactive->id, $options);
    }

    public static function localeCodes(): array
    {
        return ['en' => ['en'], 'fa' => ['fa'], 'fr' => ['fr']];
    }

    public function test_a_guest_cannot_mount_the_page(): void
    {
        Livewire::test(StubReportPage::class)->assertForbidden();
    }

    public function test_a_user_without_the_report_permission_gets_a_403(): void
    {
        $this->actingAsUserWith(['report.view_operations', 'report.view']);

        Livewire::test(StubReportPage::class)->assertForbidden();
    }

    public function test_the_permission_mounts_the_page(): void
    {
        $this->allowed();

        Livewire::test(StubReportPage::class)->assertSuccessful();
    }

    public function test_can_access_follows_the_exact_report_permission(): void
    {
        $this->assertFalse(StubReportPage::canAccess());

        $this->actingAsUserWith(['report.view_operations']);
        $this->assertFalse(StubReportPage::canAccess());

        $this->allowed();
        $this->assertTrue(StubReportPage::canAccess());
    }

    public function test_the_panel_resolves_its_pages_without_a_fatal(): void
    {
        $pages = Filament::getPanel('dashboard')->getPages();

        $this->assertContains(StubReportPage::class, $pages);
        $this->assertNotContains(ReportPage::class, $pages);
    }

    public function test_the_registry_maps_the_report_key_to_the_page_class(): void
    {
        $this->assertSame(StubReportPage::class, ReportPage::registry()['fx_supply']);
    }

    public function test_the_filters_form_renders_its_fields(): void
    {
        $this->allowed();

        Livewire::test(StubReportPage::class)
            ->assertSee(__('resources/bankProfile/strings.filters.registered_order'))
            ->assertSee(__('resources/bankProfile/strings.filters.bank'));
    }

    public function test_updating_a_filter_resets_the_table_page_and_dispatches_the_event(): void
    {
        $this->allowed();
        BankProfile::factory()->count(26)->create(['registered_order_id' => $this->order->id]);

        Livewire::test(StubReportPage::class)
            ->set('filters.registered_order_id', $this->order->id)
            ->set('paginators.page', 2)
            ->set('filters.bank_id', null)
            ->assertSet('paginators.page', 1)
            ->assertDispatched('report-filters-changed');
    }

    public function test_the_filter_object_and_summary_follow_the_filters(): void
    {
        $this->allowed();
        BankProfile::factory()->count(3)->create(['registered_order_id' => $this->order->id]);

        $component = Livewire::test(StubReportPage::class)->set('filters.registered_order_id', $this->order->id);

        $this->assertSame($this->order->id, $component->instance()->filterObject->registeredOrderId);
        $component->assertSee('Stub profiles')->assertSee('Stub rows');
        $this->assertSame('3', $component->instance()->summary[0]['lines'][0]['value']);
    }

    public function test_clicking_a_stat_line_toggles_the_quick_filter_and_rejects_unsafe_keys(): void
    {
        $this->allowed();

        Livewire::test(StubReportPage::class)
            ->call('filterTableBy', 'queued_total')
            ->assertSet('quick', 'queued_total')
            ->call('filterTableBy', 'queued_total')
            ->assertSet('quick', null)
            ->call('filterTableBy', 'x')
            ->call('filterTableBy', null)
            ->assertSet('quick', null)
            ->call('filterTableBy', 'bad key; drop')
            ->assertSet('quick', null);
    }

    public function test_the_banner_is_hidden_without_attention_and_shown_with_it(): void
    {
        $this->allowed();
        $component = Livewire::test(StubReportPage::class)->set('filters.registered_order_id', $this->order->id);

        $this->assertSame(0, $component->instance()->banner['total']);
        $component->assertDontSee(__('resources/report/strings.banner.open_attention', ['count' => 0]));

        BankProfile::factory()->create(['registered_order_id' => $this->order->id, 'requested_amount' => 0]);
        CheckRegistry::invalidate();

        $fresh = Livewire::test(StubReportPage::class)->set('filters.registered_order_id', $this->order->id);
        $banner = $fresh->instance()->banner;

        $this->assertGreaterThan(0, $banner['total']);
        $this->assertSame($banner['error'] + $banner['warning'] + $banner['info'], $banner['total']);
        $fresh->assertSee(__('resources/report/strings.banner.open_attention', ['count' => $banner['total']]));
    }

    public function test_changing_a_filter_clears_the_quick_filter_and_a_stale_key_still_shows_its_clear_button(): void
    {
        $this->allowed();

        Livewire::test(StubReportPage::class)
            ->call('filterTableBy', 'queued_total')
            ->assertSet('quick', 'queued_total')
            ->set('filters.registered_order_id', $this->order->id)
            ->assertSet('quick', null);

        Livewire::withQueryParams(['q' => 'stage_999'])->test(StubReportPage::class)
            ->assertSet('quick', 'stage_999')
            ->assertSee(__('resources/report/strings.quick.active', ['label' => 'stage_999']));
    }

    public function test_the_active_quick_selection_shows_in_the_native_indicator_row_and_the_remove_control_clears_it(): void
    {
        $this->allowed();
        BankProfile::factory()->count(2)->create(['registered_order_id' => $this->order->id]);

        Livewire::test(StubReportPage::class)
            ->assertDontSee(__('resources/report/strings.quick.active', ['label' => 'queued_total']))
            ->call('filterTableBy', 'queued_total')
            ->assertSee(__('resources/report/strings.quick.active', ['label' => 'queued_total']))
            ->assertSeeHtml("removeTableFilter('quick')")
            ->call('removeTableFilter', 'quick')
            ->assertSet('quick', null)
            ->assertDontSee(__('resources/report/strings.quick.active', ['label' => 'queued_total']));
    }

    public function test_the_reset_header_action_shows_only_when_a_filter_or_quick_differs_and_returns_every_default(): void
    {
        $this->allowed();

        $page = Livewire::test(StubReportPage::class)->assertActionHidden('resetFilters');

        $page->set('filters.registered_order_id', $this->order->id)
            ->assertActionVisible('resetFilters')
            ->call('filterTableBy', 'queued_total')
            ->callAction('resetFilters')
            ->assertSet('quick', null)
            ->assertDispatched('report-severity')
            ->assertActionHidden('resetFilters');

        $this->assertNull($page->instance()->filterObject->registeredOrderId);
        $this->assertSame(ReportFilters::fromArray([])->toArray(), $page->instance()->filterObject->toArray());

        $page->call('filterTableBy', 'queued_total')->assertActionVisible('resetFilters')->callAction('resetFilters')->assertSet('quick', null);
    }

    #[DataProvider('calendars')]
    public function test_toggling_the_calendar_redirects_and_keeps_the_filters_and_quick(string $calendar): void
    {
        $this->allowed();
        session(['calendar_type' => $calendar]);

        $page = Livewire::test(StubReportPage::class)
            ->set('filters.registered_order_id', $this->order->id)
            ->call('filterTableBy', 'queued_total')
            ->call('calendarToggled')
            ->assertRedirect();

        $this->assertSame($this->order->id, $page->instance()->filterObject->registeredOrderId);
        $this->assertSame('queued_total', $page->get('quick'));
    }

    public static function calendars(): array
    {
        return ['jalali' => ['jalali'], 'gregorian' => ['gregorian']];
    }

    public function test_every_registered_report_page_builds_exactly_four_cards(): void
    {
        $filters = new ReportFilters;
        $pages = collect(Filament::getPanel('dashboard')->getPages())
            ->filter(fn (string $page): bool => is_subclass_of($page, ReportPage::class) && $page !== StubReportPage::class);

        $this->assertGreaterThanOrEqual(4, $pages->count());

        foreach ($pages as $page) {
            $this->assertCount(4, app($page::service())->summary($filters), $page);
        }
    }

    public function test_every_report_chart_builds_consistent_data_with_and_without_records(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'charts_role_'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::query()->get());
        $user->assignRole($role);
        $this->actingAs($user);
        $empty = RegisteredOrder::factory()->create();
        $charts = collect(Filament::getPanel('dashboard')->getWidgets())
            ->filter(fn (string $widget): bool => is_subclass_of($widget, ChartWidget::class) && str_starts_with($widget, 'App\\Filament\\Pages\\Reports\\'));

        $this->assertGreaterThanOrEqual(12, $charts->count());

        foreach ($charts as $chart) {
            foreach ([['registered_order_id' => $empty->id], []] as $filters) {
                $widget = new $chart;
                $widget->pageFilters = $filters;
                $data = (fn (): array => $this->getData())->call($widget);

                $this->assertArrayHasKey('labels', $data, $chart);
                $this->assertArrayHasKey('datasets', $data, $chart);

                foreach ($data['datasets'] as $dataset) {
                    $this->assertCount(count($data['labels']), $dataset['data'], $chart);
                }
            }
        }
    }

    public function test_the_export_carries_the_quick_filter_and_the_calendar_in_force(): void
    {
        $this->allowed();
        session(['calendar_type' => 'jalali']);

        Livewire::test(StubReportPage::class)
            ->call('filterTableBy', 'allocated')
            ->callAction('exportCsv');

        Queue::assertPushed(ExportReport::class, fn (ExportReport $job): bool => $job->filters['quick'] === 'allocated' && $job->calendar === 'jalali');
    }

    public function test_the_export_header_action_dispatches_the_job_with_the_filters(): void
    {
        $user = $this->allowed();

        Livewire::test(StubReportPage::class)
            ->set('filters.registered_order_id', $this->order->id)
            ->callAction('exportCsv')
            ->assertNotified(__('resources/general/strings.export.started'));

        Queue::assertPushed(ExportReport::class, fn (ExportReport $job): bool => $job->report === 'fx_supply'
            && $job->userId === $user->id
            && $job->locale === 'en'
            && $job->filters === ReportFilters::fromArray(['registered_order_id' => $this->order->id])->toArray());
    }

    public function test_the_title_heading_navigation_label_and_group_resolve_in_every_locale(): void
    {
        $seen = [];

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $page = new StubReportPage;

            $this->assertSame(__('resources/report/strings.pages.fx_supply.title', [], $locale), StubReportPage::getNavigationLabel());
            $this->assertSame(StubReportPage::getNavigationLabel(), $page->getTitle());
            $this->assertSame(__('resources/report/strings.pages.fx_supply.heading', [], $locale), $page->getHeading());
            $this->assertSame(__('resources/dashboard/strings.navigation_group.reports', [], $locale), StubReportPage::getNavigationGroup());
            $this->assertStringNotContainsString('resources/report/strings', $page->getHeading());
            $seen[] = StubReportPage::getNavigationLabel();
        }

        $this->assertCount(3, array_unique($seen));
    }

    public function test_the_page_is_hidden_from_navigation_without_the_permission(): void
    {
        $this->actingAsUserWith(['report.view_operations']);
        $panel = Filament::getCurrentOrDefaultPanel();
        $before = count($panel->getNavigationItems());

        StubReportPage::registerNavigationItems();

        $this->assertSame($before, count($panel->getNavigationItems()));
        $this->assertTrue(StubReportPage::shouldRegisterNavigation());
        $this->assertFalse(StubReportPage::canAccess());
    }

    public function test_the_widget_is_visible_only_with_the_report_permission(): void
    {
        $this->assertFalse(StubAttentionWidget::canView());

        $this->actingAsUserWith(['report.view_operations']);
        $this->assertFalse(StubAttentionWidget::canView());

        $this->actingAsUserWith(['report.view_fx_supply']);
        $this->assertTrue(StubAttentionWidget::canView());
    }

    public function test_it_renders_a_row_for_a_fixture_offender(): void
    {
        $this->actingAsUserWith(['report.view_fx_supply', 'bank_profile.view', 'bank_profile.edit']);
        $profile = $this->zero();

        $this->widget()
            ->assertSee(__('resources/report/strings.attention.heading'))
            ->assertSee($profile->bp_number)
            ->assertSee(__('resources/report/strings.attention.checks.bank_profile.requested_amount_zero.message'))
            ->assertSee(__('resources/report/strings.attention.severity.error'))
            ->assertSeeHtml(CalendarModules::url($profile));
    }

    public function test_the_record_link_is_withheld_without_edit_permission(): void
    {
        $this->actingAsUserWith(['report.view_fx_supply', 'bank_profile.view']);
        $profile = $this->zero();

        $this->widget()
            ->assertSee($profile->bp_number)
            ->assertDontSeeHtml(CalendarModules::url($profile));
    }

    public function test_an_order_without_offenders_renders_the_empty_state(): void
    {
        $this->actingAsUserWith(['report.view_fx_supply']);

        $this->widget()->assertSee(__('resources/general/strings.empty_state.heading'));
    }

    public function test_the_check_filter_narrows_the_rows(): void
    {
        $this->actingAsUserWith(['report.view_fx_supply', 'bank_profile.view']);
        $zero = $this->zero();
        $overdue = $this->overdue();

        $this->widget()->assertSee($zero->bp_number)->assertSee($overdue->bp_number);

        $zeroMessage = __('resources/report/strings.attention.checks.bank_profile.requested_amount_zero.expected');
        $overdueMessage = __('resources/report/strings.attention.checks.bank_profile.payment_overdue.expected');

        $this->widget()->filterTable('check', self::OVERDUE)->assertSee($overdueMessage)->assertDontSee($zeroMessage);
        $this->widget()->filterTable('check', self::ZERO)->assertSee($zeroMessage)->assertDontSee($overdueMessage);
    }

    public function test_the_query_count_is_constant_for_one_and_many_offenders(): void
    {
        $this->actingAsUserWith(['report.view_fx_supply', 'bank_profile.view', 'bank_profile.edit'])->can('bank_profile.edit');
        $one = RegisteredOrder::factory()->create();
        $many = RegisteredOrder::factory()->create();
        $warm = RegisteredOrder::factory()->create();
        $this->zero($one);
        collect(range(1, 7))->each(fn () => $this->zero($many));
        $this->widget($warm);

        $single = $this->queries(fn () => $this->widget($one));
        $multiple = $this->queries(fn () => $this->widget($many));

        $this->assertSame($single, $multiple);
    }

    #[DataProvider('locales')]
    public function test_sellers_buyers_and_companies(string $locale): void
    {
        $tag = $this->tag();
        $seller = Company::factory()->seller()->create($this->names("S$tag"));
        $inactiveSeller = Company::factory()->seller()->inactive()->create($this->names("IS$tag"));
        $buyer = Company::factory()->buyer()->create($this->names("B$tag"));
        $inactiveBuyer = Company::factory()->buyer()->inactive()->create($this->names("IB$tag"));

        $this->assertListsActiveNotInactive($locale, 'getSellerField', $seller, $inactiveSeller);
        $this->assertListsActiveNotInactive($locale, 'getBuyerField', $buyer, $inactiveBuyer);
        $this->assertListsActiveNotInactive($locale, 'getCompanyField', $buyer, $inactiveBuyer);
        $this->assertArrayNotHasKey($buyer->id, $this->fieldOptions('getSellerField'));
    }

    #[DataProvider('locales')]
    public function test_banks_currencies_and_cost_centers(string $locale): void
    {
        $tag = $this->tag();
        $bank = Bank::factory()->create($this->names("K$tag"));
        $inactiveBank = Bank::factory()->inactive()->create($this->names("IK$tag"));
        $currency = Currency::factory()->create($this->names("C$tag"));
        $inactiveCurrency = Currency::factory()->inactive()->create($this->names("IC$tag"));
        $department = Department::factory()->create($this->names("D$tag"));
        $inactiveDepartment = Department::factory()->inactive()->create($this->names("ID$tag"));

        $this->assertListsActiveNotInactive($locale, 'getBankField', $bank, $inactiveBank);
        $this->assertListsActiveNotInactive($locale, 'getCurrencyField', $currency, $inactiveCurrency);
        $this->assertListsActiveNotInactive($locale, 'getCostCenterField', $department, $inactiveDepartment);
    }

    #[DataProvider('locales')]
    public function test_products_use_the_localized_label(string $locale): void
    {
        app()->setLocale($locale);
        $tag = $this->tag();
        $product = Product::factory()->create($this->names("P$tag"));
        $inactive = Product::factory()->inactive()->create($this->names("IP$tag"));

        $options = $this->fieldOptions('getProductField');

        $this->assertStringContainsString($locale === 'fa' ? "FA-P$tag" : "EN-P$tag", $options[$product->id]);
        $this->assertArrayNotHasKey($inactive->id, $options);
    }

    #[DataProvider('locales')]
    public function test_categories_show_three_level_paths(string $locale): void
    {
        app()->setLocale($locale);
        $tag = $this->tag();
        $root = Category::factory()->create(['level' => 0, ...$this->names("R$tag")]);
        $child = Category::factory()->forParent($root)->create($this->names("M$tag"));
        $leaf = Category::factory()->forParent($child)->create($this->names("L$tag"));
        $inactive = Category::factory()->forParent($root)->inactive()->create($this->names("X$tag"));
        $prefix = $locale === 'fa' ? 'FA-' : 'EN-';

        $options = $this->fieldOptions('getCategoryField');

        $this->assertSame("{$prefix}R$tag", $options[$root->id]);
        $this->assertSame("{$prefix}R$tag » {$prefix}M$tag » {$prefix}L$tag", $options[$leaf->id]);
        $this->assertArrayNotHasKey($inactive->id, $options);
    }

    public function test_registered_orders_include_orders_without_any_record(): void
    {
        $order = RegisteredOrder::factory()->create();

        $options = $this->fieldOptions('getRegisteredOrderField');

        $this->assertStringContainsString($order->ro_number, $options[$order->id]);
    }

    public function test_supply_source_options_come_from_the_bank_profile_lang(): void
    {
        $this->assertSame(
            __('resources/bankProfile/strings.general.supply_sources'),
            $this->fieldOptions('getSupplySourceField')
        );
    }

    #[DataProvider('locales')]
    public function test_status_field_lists_every_live_status_in_stage_order(string $locale): void
    {
        app()->setLocale($locale);
        $type = 'ZZ Report Type '.$this->tag();
        $make = fn (string $name, ?int $order) => Status::factory()->create([
            'type' => $type,
            'english_type' => $type,
            'name' => "FA-$name",
            'english_name' => "EN-$name",
            'stage_order' => $order,
        ]);
        $second = $make('B', 2);
        $first = $make('A', 1);
        $unordered = $make('C', null);
        $trashed = $make('D', 3);
        $trashed->delete();
        $this->resetCaches();

        $options = $this->subject()::getStatusField('status_id', 'resources/report/strings.filters.status', $type, true)->getOptions();
        $prefix = $locale === 'fa' ? 'FA-' : 'EN-';

        $this->assertSame([$first->id, $second->id, $unordered->id], array_keys($options));
        $this->assertSame("{$prefix}A", $options[$first->id]);
        $this->assertArrayNotHasKey($trashed->id, $options);
    }

    public function test_status_field_without_stage_order_lists_rows_by_id(): void
    {
        $type = 'ZZ Report Type '.$this->tag();
        $a = Status::factory()->create(['type' => $type, 'english_type' => $type, 'stage_order' => null]);
        $b = Status::factory()->create(['type' => $type, 'english_type' => $type, 'stage_order' => null]);
        $this->resetCaches();

        $options = $this->subject()::getStatusField('s', 'resources/report/strings.filters.status', $type)->getOptions();

        $this->assertSame([$a->id, $b->id], array_keys($options));
    }

    public function test_bank_profile_status_field_is_multiple_and_scoped_to_the_type(): void
    {
        $field = $this->subject()::getBankProfileStatusField();

        $this->assertSame('status_id', $field->getName());
        $this->assertTrue($field->isMultiple());

        $ids = Status::where('english_type', BankProfile::TYPE_BANK_PROFILE)->pluck('id')->all();
        $this->assertEqualsCanonicalizing($ids, array_keys($field->getOptions()));
    }

    public function test_the_requester_and_purchase_request_status_fields_are_scoped_and_named_after_the_filter_keys(): void
    {
        $subject = $this->subject();
        $user = User::factory()->create(['name' => 'Zzz Requester '.uniqid()]);
        $status = Status::factory()->create(['type' => PurchaseRequest::TYPE_PURCHASE_REQUEST, 'english_type' => PurchaseRequest::TYPE_PURCHASE_REQUEST]);

        $this->assertSame('requester_id', $subject::getRequesterField()->getName());
        $this->assertSame($user->name, $subject::getRequesterField()->getOptions()[$user->id]);
        $this->assertSame('status_id', $subject::getPurchaseRequestStatusField()->getName());
        $this->assertTrue($subject::getPurchaseRequestStatusField()->isMultiple());
        $this->assertArrayHasKey($status->id, $subject::getPurchaseRequestStatusField()->getOptions());
        $this->assertSame([], array_diff(array_keys($subject::getPurchaseRequestStatusField()->getOptions()), Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)->pluck('id')->all()));
    }

    #[DataProvider('localeCodes')]
    public function test_the_show_closed_field_takes_its_label_key_and_the_new_labels_resolve_in_every_locale(string $locale): void
    {
        app()->setLocale($locale);
        $subject = $this->subject();

        $this->assertSame('show_closed', $subject::getShowClosedField('show_finished')->getName());
        $this->assertSame(__('resources/report/strings.filters.show_closed'), (string) $subject::getShowClosedField()->getLabel());

        foreach (['show_finished', 'show_finished_profiles'] as $key) {
            $label = (string) $subject::getShowClosedField($key)->getLabel();

            $this->assertSame(__("resources/report/strings.filters.{$key}"), $label);
            $this->assertStringNotContainsString('strings.', $label);

            if ($locale !== 'en') {
                $this->assertNotSame(__("resources/report/strings.filters.{$key}", [], 'en'), $label);
            }
        }

        foreach ([(string) $subject::getRequesterField()->getLabel(), (string) $subject::getPurchaseRequestStatusField()->getLabel(), __('resources/report/strings.filters.advanced'), (string) $subject::getThresholdField('supply', 'th_supply_request')->getLabel()] as $label) {
            $this->assertNotSame('', $label);
            $this->assertStringNotContainsString('strings.', $label);
        }
    }

    public function test_field_names_match_the_report_filters_keys(): void
    {
        $subject = $this->subject();
        $expected = [
            'getDateFromField' => 'date_from',
            'getDateUntilField' => 'date_until',
            'getSellerField' => 'seller_id',
            'getBuyerField' => 'buyer_id',
            'getCategoryField' => 'category_id',
            'getProductField' => 'product_id',
            'getCurrencyField' => 'currency_id',
            'getRegisteredOrderField' => 'registered_order_id',
            'getCostCenterField' => 'cost_center_id',
            'getBankField' => 'bank_id',
            'getSupplySourceField' => 'supply_source',
            'getCompanyField' => 'company_id',
            'getBankProfileStatusField' => 'status_id',
        ];

        foreach ($expected as $method => $name) {
            $this->assertSame($name, $subject::$method()->getName(), $method);
        }

        $this->assertInstanceOf(DatePicker::class, $subject::getDateFromField());
        $this->assertInstanceOf(Select::class, $subject::getBankField());
    }

    public function test_the_threshold_field_is_a_numeric_input_with_the_default_as_placeholder_and_no_form_default(): void
    {
        $subject = $this->subject();

        foreach (ReportFilters::THRESHOLD_DEFAULTS as $key => $default) {
            $field = $subject::getThresholdField($key);

            $this->assertSame(match ($key) {
                'stuck' => 'stuck_days',
                default => "th_{$key}",
            }, $field->getName(), $key);
            $this->assertSame((string) $default, $field->getPlaceholder(), $key);
            $this->assertNull($field->getDefaultState(), $key);
            $this->assertTrue($field->isNumeric(), $key);
            [$min, $max] = ReportFilters::thresholdBounds($key);
            $this->assertSame($min, $field->getMinValue(), $key);
            $this->assertSame($max, $field->getMaxValue(), $key);
        }

        $this->assertSame('idle_days', $subject::getThresholdField('idle')->getName());
        $this->assertSame((string) ReportFilters::IDLE_DAYS, $subject::getThresholdField('idle')->getPlaceholder());
        $this->assertSame(__('resources/report/strings.filters.th_stuck'), $subject::getThresholdField('stuck')->getLabel());
    }

    public function test_the_date_basis_and_include_fields_expose_labels_options_and_defaults(): void
    {
        $subject = $this->subject();
        $basis = $subject::getDateBasisField(['order_date' => 'resources/registeredOrder/strings.form.order_date', 'validity_date' => 'resources/registeredOrder/strings.form.validity_date']);

        $this->assertSame('date_basis', $basis->getName());
        $this->assertSame([
            'order_date' => __('resources/registeredOrder/strings.form.order_date'),
            'validity_date' => __('resources/registeredOrder/strings.form.validity_date'),
        ], $basis->getOptions());
        $this->assertSame(__('resources/registeredOrder/strings.form.order_date'), $basis->getPlaceholder());
        $this->assertSame('include_undated', $subject::getIncludeUndatedField()->getName());
        $this->assertTrue($subject::getIncludeUndatedField()->getDefaultState());
        $this->assertSame('include_void', $subject::getIncludeVoidField()->getName());
        $this->assertFalse($subject::getIncludeVoidField()->getDefaultState());
        $this->assertSame('purchased_currency_id', $subject::getPurchasedCurrencyField()->getName());
        $this->assertSame('stage', $subject::getOperationsStageField()->getName());
        $this->assertSame(array_keys(\App\Services\Reports\Builders\OperationsReportService::STAGES), array_keys($subject::getOperationsStageField()->getOptions()));
    }

    #[DataProvider('localeCodes')]
    public function test_the_new_filter_labels_resolve_in_every_locale(string $locale): void
    {
        app()->setLocale($locale);
        $subject = $this->subject();
        $labels = [
            (string) $subject::getDateBasisField(['a' => 'resources/report/strings.filters.date_from'])->getLabel(),
            (string) $subject::getIncludeUndatedField()->getLabel(),
            (string) $subject::getIncludeVoidField()->getLabel(),
            (string) $subject::getPurchasedCurrencyField()->getLabel(),
            (string) $subject::getRequestedCurrencyField()->getLabel(),
            (string) $subject::getOperationsStageField()->getLabel(),
            ...array_map(fn (string $key): string => (string) $subject::getThresholdField($key)->getLabel(), array_keys(ReportFilters::THRESHOLD_DEFAULTS)),
        ];

        foreach ($labels as $label) {
            $this->assertNotSame('', $label);
            $this->assertStringNotContainsString('resources/', $label);
            $this->assertStringNotContainsString('strings.', $label);
        }

        if ($locale !== 'en') {
            $this->assertNotSame(__('resources/report/strings.filters.include_undated', [], 'en'), __('resources/report/strings.filters.include_undated'));
        }
    }

    #[DataProvider('localeCodes')]
    public function test_labels_resolve_in_every_locale(string $locale): void
    {
        app()->setLocale($locale);
        $subject = $this->subject();
        $methods = [
            'getDateFromField', 'getDateUntilField', 'getSellerField', 'getBuyerField', 'getCategoryField',
            'getProductField', 'getCurrencyField', 'getRegisteredOrderField', 'getCostCenterField', 'getBankField',
            'getSupplySourceField', 'getCompanyField', 'getBankProfileStatusField',
        ];

        foreach ($methods as $method) {
            $label = (string) $subject::$method()->getLabel();
            $this->assertNotSame('', $label, $method);
            $this->assertStringNotContainsString('resources/', $label, $method);
            $this->assertStringNotContainsString('strings.', $label, $method);
        }
    }

    #[DataProvider('localeCodes')]
    public function test_every_filter_hint_resolves_in_every_locale(string $locale): void
    {
        app()->setLocale($locale);
        $s = $this->subject();
        $status = fn (string $key, string $type): mixed => $s::getStatusField($key.'_id', 'resources/report/strings.filters.'.$key, $type, true);
        $fields = [
            $s::getDateBasisField(['a' => 'resources/report/strings.filters.date_from']), $s::getIncludeUndatedField(), $s::getIncludeVoidField(),
            $s::getIncludeFlaggedInvoicesField(), $s::getIncludeOutliersField(), $s::getFallbackOrderAmountField(), $s::getValueBasisField(),
            $s::getRateBasisField(), $s::getAmountInField(), $s::getWeightBasisField(), $s::getDepartureBasisField(), $s::getArrivalBasisField(),
            $s::getClearedFromStageField(), $s::getShowClosedField(), $s::getShowClosedField('show_finished'), $s::getShowClosedField('show_finished_profiles'),
            $s::getShowArrivedField(), $s::getCommitmentStateField(), $s::getActivityWindowField(), $s::getIdleDaysField(), $s::getStuckDaysField(),
            $s::getStuckDaysField('stuck_days_stage'), $s::getStageField(), $s::getOperationsStageField(),
            $status('tracking_status', \App\Models\Shipment::TYPE_TRACKING_STATUS), $status('shipment_status', \App\Models\Shipment::TYPE_SHIPMENT_STATUS), $status('doc_status', \App\Models\Shipment::TYPE_DOC_STATUS),
            $s::getThresholdField('supply', 'th_supply_request'), $s::getThresholdField('stuck', 'stuck_days_stage'),
            ...array_map(fn (string $key) => $s::getThresholdField($key), array_keys(ReportFilters::THRESHOLD_DEFAULTS)),
        ];

        foreach ($fields as $field) {
            $hint = (string) $field->getHintIconTooltip();
            $this->assertNotSame('', $hint, $field->getName());
            $this->assertStringNotContainsString('resources/report', $hint, $field->getName());
            $this->assertStringNotContainsString('strings.', $hint, $field->getName());
            $this->assertDoesNotMatchRegularExpression('/:\w/u', $hint, $field->getName());
        }

        $this->assertNull($s::getSellerField()->getHintIconTooltip());
    }
}
