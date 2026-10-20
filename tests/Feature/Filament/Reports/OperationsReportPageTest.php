<?php

namespace Tests\Feature\Filament\Reports;

use App\Filament\Pages\Reports\Operations\OperationsActivityWidget;
use App\Filament\Pages\Reports\Operations\OperationsAttentionWidget;
use App\Filament\Pages\Reports\Operations\OperationsReport;
use App\Filament\Pages\Reports\Operations\OperationsIdleByStageChart;
use App\Filament\Pages\Reports\Operations\OperationsStageChart;
use App\Models\BankProfile;
use App\Filament\Resources\Operational\CustomResource\Enums\ClearanceStatus;
use App\Models\Company;
use App\Models\Custom;
use App\Models\Permission;
use App\Models\Product;
use App\Models\RegisteredOrder;
use App\Models\RegisteredOrderItem;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Services\Reports\Builders\OperationsReportService;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Support\ReportFilters;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationsReportPageTest extends TestCase
{
    private Company $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        Queue::fake();
        app()->setLocale('en');
        $this->seller = Company::factory()->seller()->create();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
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

    private function actingAsViewer(array $extra = [], array $modules = ['registered_order.view', 'shipment.view', 'custom.view', 'correspondence.view']): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'operations_role_'.uniqid(), 'guard_name' => 'web']);

        foreach (['report.view_operations', ...$modules, ...$extra] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function order(array $attributes = []): RegisteredOrder
    {
        return RegisteredOrder::factory()->create($attributes + ['seller_id' => $this->seller->id, 'order_date' => today()->toDateString(), 'validity_date' => null]);
    }

    private function cleanOrder(): RegisteredOrder
    {
        $order = $this->order(['validity_date' => today()->addDays(90)->toDateString()]);
        RegisteredOrderItem::factory()->create(['registered_order_id' => $order->id]);
        BankProfile::factory()->create(['registered_order_id' => $order->id]);

        return $order;
    }

    public function test_the_page_needs_the_operations_permission(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(OperationsReport::class)->assertForbidden();
    }

    public function test_the_page_lists_the_open_order_its_cards_and_columns(): void
    {
        $this->actingAsViewer();
        $order = $this->cleanOrder();

        Livewire::test(OperationsReport::class)
            ->set('filters.seller_id', $this->seller->id)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$order])
            ->assertSee(__('resources/report/strings.operations.cards.stages'))
            ->assertSee(__('resources/report/strings.operations.cards.correspondence'))
            ->assertTableColumnExists('stage')
            ->assertTableColumnExists('idle_days')
            ->assertTableColumnExists('last_activity_at')
            ->assertTableColumnExists('fx_status')
            ->assertTableColumnExists('clearance_status');
    }

    public function test_the_activity_and_threshold_fields_live_in_a_collapsed_advanced_section_with_unchanged_state_names(): void
    {
        $this->actingAsViewer();
        $page = Livewire::test(OperationsReport::class)->instance();
        $sections = collect($page->getSchema('filtersForm')->getFlatComponents())->filter(fn ($component): bool => $component instanceof Section && (string) $component->getHeading() === __('resources/report/strings.filters.advanced'));
        $advanced = ['activity_window', 'causer_id', 'module', 'idle_days', 'th_idle_error', 'th_validity', 'th_supply'];
        $top = ['date_from', 'date_until', 'date_basis', 'include_undated', 'seller_id', 'buyer_id', 'company_id', 'category_id', 'product_id', 'currency_id', 'registered_order_id', 'status_id', 'stage', 'show_closed', 'cleared_from_stage'];

        $this->assertCount(1, $sections);
        $this->assertTrue($sections->first()->isCollapsed());
        $this->assertEqualsCanonicalizing($advanced, array_keys($sections->first()->getChildSchema()->getFlatFields()));
        $this->assertEqualsCanonicalizing([...$top, ...$advanced], array_keys($page->getSchema('filtersForm')->getFlatFields()));
    }

    public function test_the_two_idle_lines_are_called_warning_and_critical_with_their_thresholds_in_every_locale(): void
    {
        foreach (['en' => ['Idle (warning)', 'Idle (critical)'], 'fa' => ['هشدار', 'بحرانی'], 'fr' => ['avertissement', 'critique']] as $locale => [$warning, $critical]) {
            app()->setLocale($locale);
            $lines = collect(app(OperationsReportService::class)->summary(ReportFilters::fromArray(['idle_days' => 5, 'th_idle_error' => 20])))->firstWhere('key', 'idle')['lines'];

            $this->assertStringContainsString($warning, $lines[0]['label'], $locale);
            $this->assertStringContainsString('5', $lines[0]['label'], $locale);
            $this->assertStringContainsString($critical, $lines[1]['label'], $locale);
            $this->assertStringContainsString('20', $lines[1]['label'], $locale);
            $this->assertSame(['idle_threshold', 'idle_over_14'], array_column($lines, 'quick'), $locale);
        }
    }

    public function test_the_table_can_be_grouped_by_seller_and_by_stage(): void
    {
        $this->actingAsViewer();
        $order = $this->cleanOrder();
        $table = Livewire::test(OperationsReport::class)->set('filters.seller_id', $this->seller->id);

        $this->assertSame(['seller', 'stage'], array_keys($table->instance()->getTable()->getGroups()));

        foreach (['seller', 'stage'] as $group) {
            $table->set('tableGrouping', $group)->assertSuccessful()->assertCanSeeTableRecords([$order]);
        }

        $this->assertSame($this->seller->getLocalizedNameAttribute(), $table->instance()->getTable()->getGroups()['seller']->getTitle($order->load('sellerCompany')));
        $this->assertSame(__(OperationsReportService::STAGES['registration']), $table->instance()->getTable()->getGroups()['stage']->getTitle(RegisteredOrder::query()->selectRaw("registered_orders.*, 'registration' as stage")->find($order->id)));
    }

    public function test_the_filter_accordion_holds_the_operations_fields_and_the_shared_ones(): void
    {
        $this->actingAsViewer();

        Livewire::test(OperationsReport::class)
            ->assertSee(__('resources/report/strings.filters.heading'))
            ->assertSee(__('resources/report/strings.filters.activity_window'))
            ->assertSee(__('resources/report/strings.filters.causer'))
            ->assertSee(__('resources/report/strings.filters.module'))
            ->assertSee(__('resources/report/strings.filters.idle_days'))
            ->assertSee(__('resources/report/strings.filters.seller'))
            ->assertSee(__('resources/report/strings.filters.status'));
    }

    public function test_the_self_service_filters_are_present_and_their_defaults_live_in_the_filter_object(): void
    {
        $this->actingAsViewer();

        $page = Livewire::test(OperationsReport::class);

        foreach (['date_basis', 'include_undated', 'show_closed', 'cleared_from_stage', 'stage', 'th_idle_error', 'th_validity', 'th_supply'] as $key) {
            $page->assertSee(__("resources/report/strings.filters.{$key}"));
        }

        $page->assertSee(__('resources/bankProfile/strings.filters.company'));
        $this->assertTrue($page->instance()->filterObject->includeUndated);
        $this->assertFalse($page->instance()->filterObject->showClosed);
        $this->assertSame(14, $page->instance()->filterObject->threshold('idle_error'));
    }

    public function test_switching_the_threshold_filters_changes_the_card_labels(): void
    {
        $this->actingAsViewer();

        Livewire::test(OperationsReport::class)
            ->set('filters.th_idle_error', 21)
            ->set('filters.th_validity', 9)
            ->assertSee(__('resources/report/strings.operations.lines.idle_critical', ['days' => 21]))
            ->assertSee(__('resources/report/strings.operations.lines.expiring', ['days' => 9]));
    }

    public function test_the_closed_toggle_shows_cleared_orders_in_the_table(): void
    {
        $this->actingAsViewer();
        $order = $this->order();
        $shipment = Shipment::factory()->create(['registered_order_id' => $order->id]);
        $hiddenLine = fn ($page): ?string => collect($page->instance()->summary[0]['lines'])->firstWhere('label', __('resources/report/strings.operations.lines.closed_hidden'))['value'] ?? null;
        $completed = Status::firstOrCreate(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'english_name' => ClearanceStatus::Completed->value], ['type' => Custom::TYPE_CLEARANCE_STATUS, 'name' => ClearanceStatus::Completed->value]);
        Custom::factory()->create(['registered_order_id' => $order->id, 'shipment_id' => $shipment->id, 'clearance_status_id' => $completed->id]);

        $page = Livewire::test(OperationsReport::class)
            ->set('filters.seller_id', $this->seller->id)
            ->assertCanNotSeeTableRecords([$order]);

        $this->assertSame('1', $hiddenLine($page));

        $page->set('filters.show_closed', true)->assertCanSeeTableRecords([$order]);

        $this->assertNull($hiddenLine($page));
    }

    public function test_the_added_columns_exist_sort_and_the_product_search_finds_the_order(): void
    {
        $this->actingAsViewer();
        $order = $this->order();
        $product = Product::factory()->create(['name' => 'Zebrazinc '.uniqid()]);
        RegisteredOrderItem::factory()->create(['registered_order_id' => $order->id, 'product_id' => $product->id]);
        $other = $this->order();

        Livewire::test(OperationsReport::class)
            ->set('filters.seller_id', $this->seller->id)
            ->assertTableColumnExists('purchase_orders_count')
            ->assertTableColumnExists('order_date')
            ->assertTableColumnExists('buyerCompany.name')
            ->assertTableColumnExists('currency.name')
            ->sortTable('purchase_orders_count')
            ->assertSuccessful()
            ->searchTable('Zebrazinc')
            ->assertCanSeeTableRecords([$order])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_the_guide_resolves_the_threshold_placeholders_from_the_filters_in_every_locale(): void
    {
        $this->actingAsViewer();

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $page = Livewire::test(OperationsReport::class)->set('filters.th_idle_error', 21)->set('filters.idle_days', 4);
            $guide = $page->instance()->guide;
            $encoded = json_encode($guide, JSON_UNESCAPED_UNICODE);

            $this->assertStringNotContainsString(':th_', $encoded, $locale);
            $this->assertStringContainsString('21', $guide['sections']['filters']['items']['th_idle_error']['how'], $locale);
            $this->assertStringContainsString('4', $guide['sections']['filters']['items']['idle_days']['how'], $locale);
            $this->assertStringContainsString('21', $guide['sections']['cards']['items']['idle']['how'], $locale);
        }

        app()->setLocale('en');
    }

    public function test_the_idle_days_filter_reaches_the_cards(): void
    {
        $this->actingAsViewer();
        $this->cleanOrder();

        Livewire::withQueryParams(['filters' => ['seller_id' => $this->seller->id, 'idle_days' => 3]])->test(OperationsReport::class)
            ->assertSee(__('resources/report/strings.operations.lines.idle_warning', ['days' => 3]));
    }

    public function test_the_page_embeds_the_chart_the_feed_and_the_attention_table_when_there_are_issues(): void
    {
        $this->actingAsViewer();
        $this->order(['validity_date' => today()->subDay()->toDateString()]);

        Livewire::withQueryParams(['filters' => ['seller_id' => $this->seller->id]])->test(OperationsReport::class)
            ->assertSeeLivewire(OperationsStageChart::class)
            ->assertSeeLivewire(OperationsIdleByStageChart::class)
            ->assertSeeLivewire(OperationsActivityWidget::class)
            ->assertSeeLivewire(OperationsAttentionWidget::class);
    }

    public function test_the_issues_section_is_hidden_when_the_report_is_clean(): void
    {
        $this->actingAsViewer();
        $this->cleanOrder();

        Livewire::withQueryParams(['filters' => ['seller_id' => $this->seller->id]])->test(OperationsReport::class)
            ->assertSeeLivewire(OperationsStageChart::class)
            ->assertSeeLivewire(OperationsIdleByStageChart::class)
            ->assertSeeLivewire(OperationsActivityWidget::class)
            ->assertDontSeeLivewire(OperationsAttentionWidget::class);
    }

    public function test_the_attention_table_lists_the_operations_issue_texts(): void
    {
        $this->actingAsViewer();
        $this->order(['validity_date' => today()->subDay()->toDateString()]);

        Livewire::test(OperationsAttentionWidget::class, ['pageFilters' => ['seller_id' => $this->seller->id]])
            ->assertSee(__('resources/report/strings.attention.checks.registered_order.validity_expired.expected'))
            ->assertSee(__('resources/report/strings.attention.checks.registered_order.no_items.expected'));
    }

    public function test_clicking_a_stage_line_narrows_the_table_and_a_second_click_clears_it(): void
    {
        $this->actingAsViewer();
        $plain = $this->order();
        $banked = $this->order();
        BankProfile::factory()->create(['registered_order_id' => $banked->id]);
        $chip = __('resources/report/strings.quick.active', ['label' => __('resources/report/strings.operations.cards.stages').' — '.__('resources/bankProfile/strings.general.model_label')]);

        Livewire::withQueryParams(['filters' => ['seller_id' => $this->seller->id]])->test(OperationsReport::class)
            ->assertCanSeeTableRecords([$plain, $banked])
            ->call('filterTableBy', 'stage_bank_profile')
            ->assertCanSeeTableRecords([$banked])
            ->assertCanNotSeeTableRecords([$plain])
            ->assertSee($chip)
            ->assertSeeHtml("filterTableBy('stage_bank_profile')")
            ->call('removeTableFilter', 'quick')
            ->assertSet('quick', null)
            ->assertCanSeeTableRecords([$plain, $banked])
            ->assertDontSee($chip)
            ->call('filterTableBy', 'stage_bank_profile')
            ->call('removeTableFilters')
            ->assertSet('quick', null)
            ->assertDontSee($chip);
    }

    public function test_the_url_param_applies_the_quick_filter_and_the_cards_keep_their_numbers(): void
    {
        $this->actingAsViewer();
        $expired = $this->order(['validity_date' => today()->subDay()->toDateString()]);
        $fresh = $this->order();
        $filters = ['filters' => ['seller_id' => $this->seller->id]];
        $summary = Livewire::withQueryParams($filters)->test(OperationsReport::class)->instance()->summary;

        $page = Livewire::withQueryParams($filters + ['q' => 'expired'])->test(OperationsReport::class)
            ->assertCanSeeTableRecords([$expired])
            ->assertCanNotSeeTableRecords([$fresh]);

        $this->assertSame($summary, $page->instance()->summary);
        $page->call('filterTableBy', null)->assertCanSeeTableRecords([$expired, $fresh]);
    }

    public function test_the_table_total_equals_every_clickable_line_value(): void
    {
        $this->actingAsViewer();
        $this->order(['validity_date' => today()->addDays(5)->toDateString()]);
        $this->order(['validity_date' => today()->subDay()->toDateString()]);
        $banked = $this->order();
        BankProfile::factory()->create(['registered_order_id' => $banked->id]);
        $page = Livewire::withQueryParams(['filters' => ['seller_id' => $this->seller->id]])->test(OperationsReport::class);

        foreach (collect($page->instance()->summary)->flatMap(fn (array $card): array => $card['lines'])->filter(fn (array $line): bool => isset($line['quick'])) as $line) {
            $page->call('filterTableBy', $line['quick']);
            $this->assertSame((int) $line['value'], $page->instance()->getTable()->getRecords()->total(), $line['quick']);
            $page->call('filterTableBy', $line['quick']);
        }
    }

    public function test_the_export_action_is_available(): void
    {
        $this->actingAsViewer();

        Livewire::test(OperationsReport::class)->assertActionExists('exportCsv');
    }

    public function test_every_operations_widget_is_registered_in_the_panel_and_gated(): void
    {
        $widgets = Filament::getPanel('dashboard')->getWidgets();
        $classes = [OperationsAttentionWidget::class, OperationsStageChart::class, OperationsIdleByStageChart::class, OperationsActivityWidget::class];

        foreach ($classes as $widget) {
            $this->assertContains($widget, $widgets);
            $this->assertFalse($widget::canView());
        }

        $this->actingAsViewer();

        foreach ($classes as $widget) {
            $this->assertTrue($widget::canView());
        }
    }

    public function test_the_feed_shows_only_models_the_viewer_may_view(): void
    {
        $actor = $this->actingAsViewer(modules: []);
        $order = $this->order();
        $filters = ['causer_id' => $actor->id];

        Livewire::test(OperationsActivityWidget::class, ['pageFilters' => $filters])
            ->assertDontSee($order->ro_number);

        $actor->roles->first()->givePermissionTo(Permission::firstOrCreate(['name' => 'registered_order.view', 'guard_name' => 'web']));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::test(OperationsActivityWidget::class, ['pageFilters' => $filters])
            ->assertSee($order->ro_number)
            ->assertSee(__('resources/report/strings.operations.feed.heading'));
    }

    public function test_the_stage_chart_renders_for_the_filtered_seller(): void
    {
        $this->actingAsViewer();
        $this->cleanOrder();

        Livewire::test(OperationsStageChart::class, ['pageFilters' => ['seller_id' => $this->seller->id]])->assertSuccessful();
    }

    public function test_the_guide_explains_every_filter_card_chart_column_and_issue_in_all_locales(): void
    {
        $this->actingAsViewer();
        $page = Livewire::test(OperationsReport::class);
        $expected = [
            'filters' => array_keys($page->instance()->filtersForm(Schema::make($page->instance()))->getFlatFields(withHidden: true)),
            'cards' => array_column(app(OperationsReportService::class)->summary(new ReportFilters), 'key'),
            'charts' => ['stages', 'idle_by_stage', 'activity_feed'],
            'table' => array_keys($page->instance()->getTable()->getColumns()),
            'issues' => app(CheckRegistry::class)->forReport('operations')->map(fn ($check): string => str_replace('.', '_', $check->key))->all(),
        ];

        $this->assertContains('idle_days', $expected['filters']);
        $this->assertCount(12, $expected['issues']);

        foreach (['en', 'fa', 'fr'] as $locale) {
            $guide = require lang_path("{$locale}/resources/report/guide_operations.php");

            $this->assertNotSame('', trim($guide['intro']), $locale);

            foreach ($expected as $section => $keys) {
                $this->assertNotSame('', trim((string) ($guide['sections'][$section]['title'] ?? '')), "{$locale}.{$section} title");
                $items = $guide['sections'][$section]['items'] ?? [];

                foreach ($keys as $key) {
                    $this->assertArrayHasKey($key, $items, "{$locale}.{$section}.{$key}");
                    $this->assertNotSame('', trim((string) ($items[$key]['title'] ?? '')), "{$locale}.{$section}.{$key} title");
                    $this->assertNotSame('', trim((string) ($items[$key]['meaning'] ?? '')), "{$locale}.{$section}.{$key} meaning");

                    if ($section === 'issues') {
                        $this->assertNotSame('', trim((string) ($items[$key]['how'] ?? '')), "{$locale}.{$key} how");
                        $this->assertNotSame('', trim((string) ($items[$key]['change'] ?? '')), "{$locale}.{$key} change");
                    }
                }
            }

            $this->assertStringNotContainsString('TODO', json_encode($guide, JSON_UNESCAPED_UNICODE));
        }
    }
}
