<?php

namespace Tests\Feature\Filament\Reports;

use App\Filament\Pages\Reports\FxSupply\FxSupplyReport;
use App\Filament\Pages\Reports\FxSupply\FxSupplyAttentionWidget;
use App\Filament\Pages\Reports\FxSupply\FxSupplyByBankChart;
use App\Filament\Pages\Reports\FxSupply\FxSupplyFunnelChart;
use App\Models\BankProfile;
use App\Models\Permission;
use App\Models\RegisteredOrder;
use App\Models\Status;
use App\Models\Role;
use App\Models\User;
use App\Services\Reports\Builders\FxSupplyReportService;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Support\ReportFilters;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class FxSupplyReportPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        app()->setLocale('en');
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

    private function actingAsReportViewer(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'fx_supply_role_'.uniqid(), 'guard_name' => 'web']);
        foreach (['report.view_fx_supply', 'bank_profile.view'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $user->assignRole($role);
        $this->actingAs($user);
    }

    public function test_the_page_needs_the_fx_supply_permission(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(FxSupplyReport::class)->assertForbidden();
    }

    public function test_the_page_lists_a_profile_and_its_cards(): void
    {
        $this->actingAsReportViewer();
        $profile = BankProfile::factory()->create(['registered_order_id' => RegisteredOrder::factory()->create()->id]);

        Livewire::test(FxSupplyReport::class)
            ->set('filters.registered_order_id', $profile->registered_order_id)
            ->assertSuccessful()
            ->assertSee($profile->bp_number)
            ->assertSee(__('resources/report/strings.fx_supply.cards.queued'));
    }

    public function test_the_page_embeds_both_charts_and_the_attention_table(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        BankProfile::factory()->create(['registered_order_id' => $order->id, 'requested_amount' => 0]);

        Livewire::withQueryParams(['filters' => ['registered_order_id' => $order->id]])->test(FxSupplyReport::class)
            ->assertSeeLivewire(FxSupplyFunnelChart::class)
            ->assertSeeLivewire(FxSupplyByBankChart::class)
            ->assertSeeLivewire(FxSupplyAttentionWidget::class);
    }

    public function test_the_attention_section_is_hidden_when_the_report_has_no_issues(): void
    {
        $this->actingAsReportViewer();
        $clean = RegisteredOrder::factory()->create();

        Livewire::withQueryParams(['filters' => ['registered_order_id' => $clean->id]])->test(FxSupplyReport::class)
            ->assertSeeLivewire(FxSupplyFunnelChart::class)
            ->assertDontSeeLivewire(FxSupplyAttentionWidget::class);
    }

    public function test_the_banner_severity_event_narrows_the_attention_table_and_show_all_clears_it(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $allocated = Status::factory()->create(['type' => BankProfile::TYPE_BANK_PROFILE, 'english_type' => BankProfile::TYPE_BANK_PROFILE, 'name' => 'Allocated', 'english_name' => 'Allocated']);
        BankProfile::factory()->create(['registered_order_id' => $order->id, 'status_id' => $allocated->id, 'requested_amount' => 0, 'payment_due_date' => null]);
        BankProfile::factory()->create(['registered_order_id' => $order->id, 'status_id' => $allocated->id, 'requested_amount' => 1000, 'payment_due_date' => today()->addDays(5)->toDateString()]);
        $error = __('resources/report/strings.attention.checks.bank_profile.requested_amount_zero.expected');
        $warning = __('resources/report/strings.attention.checks.bank_profile.payment_due_soon.expected', ['days' => 14]);

        $widget = Livewire::test(FxSupplyAttentionWidget::class, ['pageFilters' => ['registered_order_id' => $order->id]])
            ->assertSee($error)
            ->assertSee($warning);

        $widget->dispatch('report-severity', severity: 'warning')->assertSee($warning)->assertDontSee($error);
        $widget->dispatch('report-severity', severity: 'error')->assertSee($error)->assertDontSee($warning);
        $widget->dispatch('report-severity', severity: null)->assertSee($error)->assertSee($warning);
    }

    public function test_the_self_service_fields_render_and_their_defaults_live_in_the_filter_object(): void
    {
        $this->actingAsReportViewer();

        $page = Livewire::test(FxSupplyReport::class);

        foreach (['date_basis', 'include_undated', 'th_stuck', 'th_due_soon'] as $key) {
            $page->assertSee(__("resources/report/strings.filters.{$key}"));
        }

        $page->assertSee(__('resources/bankProfile/strings.form.requested_currency'))->assertSee(__('resources/bankProfile/strings.form.purchased_currency'));
        $this->assertTrue($page->instance()->filterObject->includeUndated);
        $this->assertSame(30, $page->instance()->filterObject->threshold('stuck'));
        $this->assertSame(14, $page->instance()->filterObject->threshold('due_soon'));
    }

    public function test_finished_profiles_are_hidden_until_the_toggle_is_on_and_the_payment_lines_still_show_them(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $status = fn (string $name): int => Status::factory()->create(['type' => BankProfile::TYPE_BANK_PROFILE, 'english_type' => BankProfile::TYPE_BANK_PROFILE, 'name' => $name, 'english_name' => $name])->id;
        $open = BankProfile::factory()->create(['registered_order_id' => $order->id, 'status_id' => $status('Allocated'), 'payment_due_date' => null]);
        $done = BankProfile::factory()->create(['registered_order_id' => $order->id, 'status_id' => $status('Purchased'), 'payment_due_date' => today()->subDay()->toDateString()]);
        $note = fn ($page): ?array => collect($page->instance()->summary[3]['lines'])->firstWhere('label', __('resources/report/strings.fx_supply.lines.finished_hidden'));

        $page = Livewire::test(FxSupplyReport::class)->set('filters.registered_order_id', $order->id)
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$done])
            ->assertSee(__('resources/report/strings.fx_supply.lines.finished_hidden'))
            ->assertSee(__('resources/report/strings.fx_supply.cards.timing_payments'))
            ->assertSeeHtml("filterTableBy('payment_overdue')")
            ->assertSeeHtml("filterTableBy('payment_due_soon')")
            ->call('filterTableBy', 'payment_overdue')
            ->assertCanSeeTableRecords([$done])
            ->assertCanNotSeeTableRecords([$open])
            ->call('filterTableBy', 'payment_overdue');

        $this->assertSame('1', $note($page)['value']);
        $this->assertArrayNotHasKey('quick', $note($page));

        $page->set('filters.show_closed', true)->assertCanSeeTableRecords([$open, $done]);

        $this->assertNull($note($page));
    }

    public function test_the_table_can_be_grouped_by_bank(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $profile = BankProfile::factory()->create(['registered_order_id' => $order->id, 'status_id' => null]);
        $table = Livewire::test(FxSupplyReport::class)->set('filters.registered_order_id', $order->id);
        $group = $table->instance()->getTable()->getGroups()['bank'];

        $this->assertSame(['bank'], array_keys($table->instance()->getTable()->getGroups()));
        $table->set('tableGrouping', 'bank')->assertSuccessful()->assertCanSeeTableRecords([$profile]);
        $this->assertSame($profile->bank?->getLocalizedNameAttribute() ?? '-', $group->getTitle($profile));
    }

    public function test_the_hidden_columns_exist_and_the_days_colour_follows_the_stuck_threshold(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        BankProfile::factory()->create(['registered_order_id' => $order->id]);

        $page = Livewire::withQueryParams(['filters' => ['registered_order_id' => $order->id]])->test(FxSupplyReport::class)
            ->assertTableColumnExists('supply_source')
            ->assertTableColumnExists('final_rate')
            ->assertTableColumnExists('commission_rate')
            ->assertTableColumnExists('targetable.name');

        $this->assertSame('gray', $page->instance()->getTable()->getColumn('days_in_status')->getColor(30));
        $this->assertSame('danger', $page->instance()->getTable()->getColumn('days_in_status')->getColor(31));

        $page->set('filters.stuck_days', 10);

        $this->assertSame(10, $page->instance()->filterObject->threshold('stuck'));
        $this->assertSame('danger', $page->instance()->getTable()->getColumn('days_in_status')->getColor(11));
        $this->assertSame('gray', $page->instance()->getTable()->getColumn('days_in_status')->getColor(10));
    }

    public function test_every_report_widget_is_registered_in_the_panel_and_gated(): void
    {
        $widgets = Filament::getPanel('dashboard')->getWidgets();

        foreach ([FxSupplyAttentionWidget::class, FxSupplyFunnelChart::class, FxSupplyByBankChart::class] as $widget) {
            $this->assertContains($widget, $widgets);
        }

        $this->assertFalse(FxSupplyFunnelChart::canView());

        $this->actingAsReportViewer();

        $this->assertTrue(FxSupplyFunnelChart::canView());
        $this->assertTrue(FxSupplyByBankChart::canView());
        $this->assertTrue(FxSupplyAttentionWidget::canView());
    }

    public function test_the_guide_covers_every_filter_card_chart_column_and_check_in_every_language(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $filters = ReportFilters::fromArray(['registered_order_id' => $order->id]);
        $fields = ['date_from', 'date_until', 'date_basis', 'include_undated', 'bank_id', 'company_id', 'registered_order_id', 'currency_id', 'purchased_currency_id', 'supply_source', 'status_id', 'show_closed', 'stuck_days', 'th_due_soon'];
        $columns = ['bp_number', 'registered_order', 'bank', 'company', 'targetable', 'status', 'days_in_status', 'requested_amount', 'requested_currency', 'purchased_equivalent', 'supply_source', 'final_rate', 'commission_rate', 'creation_date', 'allocation_date', 'purchase_date', 'payment_due_date', 'toggle', 'export'];
        $mounted = array_keys(Livewire::test(FxSupplyReport::class)->instance()->filtersForm->getFlatFields(withHidden: true));
        $this->assertEqualsCanonicalizing($fields, array_map(fn (string $path): string => last(explode('.', $path)), $mounted));
        $checks = app(CheckRegistry::class)->forReport('fx_supply')->map(fn ($check): string => str_replace('.', '_', $check->key));
        $cards = collect(app(FxSupplyReportService::class)->summary($filters))->pluck('key');

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $guide = Livewire::test(FxSupplyReport::class)->instance()->guide;
            $this->assertNotSame('', $guide['intro']);
            $this->assertSame(['filters', 'cards', 'charts', 'table', 'issues'], array_keys($guide['sections']));

            $expected = ['filters' => $fields, 'cards' => $cards->all(), 'charts' => ['funnel', 'by_bank'], 'table' => $columns, 'issues' => $checks->all()];

            foreach ($expected as $section => $keys) {
                $this->assertNotEmpty($guide['sections'][$section]['title']);

                foreach ($keys as $key) {
                    $this->assertNotEmpty($guide['sections'][$section]['items'][$key]['title'] ?? null, "{$locale} {$section} {$key} title");
                }
            }

            foreach ($checks as $key) {
                foreach (['meaning', 'how', 'change'] as $part) {
                    $this->assertNotEmpty($guide['sections']['issues']['items'][$key][$part] ?? null, "{$locale} {$key} {$part}");
                }
            }

            array_walk_recursive($guide, fn ($text) => $this->assertDoesNotMatchRegularExpression('/TODO|:[a-z_]+\b/u', (string) $text));
        }
    }

    private function quickProfiles(): array
    {
        $order = RegisteredOrder::factory()->create();
        $status = fn (string $name): int => Status::factory()->create(['type' => BankProfile::TYPE_BANK_PROFILE, 'english_type' => BankProfile::TYPE_BANK_PROFILE, 'name' => $name, 'english_name' => $name])->id;
        $make = fn (string $name, float $amount = 100): BankProfile => BankProfile::factory()->create(['registered_order_id' => $order->id, 'status_id' => $status($name), 'requested_amount' => $amount]);

        return [$order, $make('Awaiting Supply'), $make('Allocated'), $make('Allocated', 200)];
    }

    public function test_clicking_a_line_narrows_the_table_and_clicking_it_again_clears_it(): void
    {
        $this->actingAsReportViewer();
        [$order, $queued, $allocatedA, $allocatedB] = $this->quickProfiles();

        $page = Livewire::test(FxSupplyReport::class)
            ->set('filters.registered_order_id', $order->id)
            ->assertCanSeeTableRecords([$queued, $allocatedA, $allocatedB])
            ->call('filterTableBy', 'allocated')
            ->assertCanSeeTableRecords([$allocatedA, $allocatedB])
            ->assertCanNotSeeTableRecords([$queued])
            ->assertSee(__('resources/report/strings.quick.active', ['label' => __('resources/report/strings.fx_supply.cards.allocated').' — '.__('resources/report/strings.fx_supply.lines.count')]))
            ->assertSeeHtml("removeTableFilter('quick')");

        $page->call('filterTableBy', 'allocated')
            ->assertCanSeeTableRecords([$queued, $allocatedA, $allocatedB])
            ->assertDontSeeHtml("removeTableFilter('quick')");
    }

    public function test_the_quick_url_parameter_applies_and_show_all_rows_clears_it(): void
    {
        $this->actingAsReportViewer();
        [$order, $queued, $allocatedA] = $this->quickProfiles();

        Livewire::withQueryParams(['filters' => ['registered_order_id' => $order->id], 'q' => 'queued'])->test(FxSupplyReport::class)
            ->assertCanSeeTableRecords([$queued])
            ->assertCanNotSeeTableRecords([$allocatedA])
            ->call('filterTableBy', null)
            ->assertCanSeeTableRecords([$queued, $allocatedA]);
    }

    public function test_count_and_currency_lines_render_as_clickable_and_other_lines_do_not(): void
    {
        $this->actingAsReportViewer();
        [$order, , $allocatedA] = $this->quickProfiles();

        $page = Livewire::withQueryParams(['filters' => ['registered_order_id' => $order->id]])->test(FxSupplyReport::class)
            ->assertSeeHtml("filterTableBy('allocated')")
            ->assertSeeHtml("filterTableBy('allocated_currency_{$allocatedA->requested_currency_id}')")
            ->assertSeeHtml("filterTableBy('queued')");

        $this->assertStringNotContainsString("filterTableBy('average_days", $page->html());
        $this->assertStringNotContainsString("filterTableBy('eur", $page->html());
    }

    public function test_cards_keep_their_numbers_while_a_line_is_clicked(): void
    {
        $this->actingAsReportViewer();
        [$order] = $this->quickProfiles();
        $page = Livewire::withQueryParams(['filters' => ['registered_order_id' => $order->id]])->test(FxSupplyReport::class);
        $before = $page->instance()->summary;

        $page->call('filterTableBy', 'queued');

        $this->assertSame($before, $page->instance()->summary);
    }

    public function test_the_charts_render_for_the_filtered_order(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();

        Livewire::test(FxSupplyFunnelChart::class, ['pageFilters' => ['registered_order_id' => $order->id]])->assertSuccessful();
        Livewire::test(FxSupplyByBankChart::class, ['pageFilters' => ['registered_order_id' => $order->id]])->assertSuccessful();
    }

    public function test_the_stage_chart_is_a_doughnut_with_one_colour_per_slice_and_the_bank_chart_stays_a_bar(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $chart = Livewire::test(FxSupplyFunnelChart::class, ['pageFilters' => ['registered_order_id' => $order->id]])->instance();
        $bank = Livewire::test(FxSupplyByBankChart::class, ['pageFilters' => ['registered_order_id' => $order->id]])->instance();
        $read = fn (string $method) => (fn () => $this->{$method}())->call($chart);
        $data = $read('getData');

        $this->assertSame('doughnut', $read('getType'));
        $this->assertSame('bar', (fn () => $this->getType())->call($bank));
        $this->assertCount(count($data['labels']), $data['datasets'][0]['backgroundColor']);
        $this->assertSame('bottom', $read('getOptions')['plugins']['legend']['position']);
    }
}
