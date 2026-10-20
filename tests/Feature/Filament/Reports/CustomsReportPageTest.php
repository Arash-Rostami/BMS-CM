<?php

namespace Tests\Feature\Filament\Reports;

use App\Filament\Pages\Reports\Customs\CustomsAttentionWidget;
use App\Filament\Pages\Reports\Customs\CustomsClearanceByMonthChart;
use App\Filament\Pages\Reports\Customs\CustomsReport;
use App\Filament\Pages\Reports\Customs\CustomsStageChart;
use App\Models\Company;
use App\Models\Custom;
use App\Models\Permission;
use App\Models\Product;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Status;
use App\Models\User;
use App\Services\Reports\Builders\CustomsReportService;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Support\ReportFilters;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CustomsReportPageTest extends TestCase
{
    private array $statuses = [];

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
        $role = Role::create(['name' => 'customs_role_'.uniqid(), 'guard_name' => 'web']);

        foreach (['report.view_customs', 'custom.view', 'shipment.view'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $user->assignRole($role);
        $this->actingAs($user);
    }

    public function test_the_document_submission_column_renders_in_the_active_calendar(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $this->customsFile($order, ['doc_submission_date' => '2025-03-20']);

        foreach (['gregorian' => toGregorianDate('2025-03-20'), 'jalali' => toPersianDate('2025-03-20')] as $calendar => $expected) {
            session(['calendar_type' => $calendar]);

            Livewire::test(CustomsReport::class)->set('filters.registered_order_id', $order->id)->assertSee($expected);
        }

        session()->forget('calendar_type');
    }

    private function clearanceStatus(string $name): Status
    {
        return $this->statuses[$name] ??= Status::firstOrCreate(
            ['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'english_name' => $name],
            ['type' => Custom::TYPE_CLEARANCE_STATUS, 'name' => $name],
        );
    }

    private function customsFile(RegisteredOrder $order, array $attributes = []): Custom
    {
        $shipment = Shipment::factory()->create([
            'registered_order_id' => $order->id,
            'warehouse_date' => today()->subDays(12)->toDateString(),
            'customs_quantity' => 10,
            'shipped_quantity' => 50,
        ]);

        return Custom::factory()->create($attributes + [
            'registered_order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'clearance_status_id' => $this->clearanceStatus('Declared')->id,
            'bank_guarantee_status_id' => null,
            'commitment_status_id' => null,
            'clearance_date' => null,
            'doc_submission_date' => today()->subDays(10)->toDateString(),
            'ten_percent_exit_date' => today()->subDays(2)->toDateString(),
            'clearance_type' => 'definitive',
        ]);
    }

    public function test_the_doc_status_and_seller_columns_show_the_english_name_when_the_persian_name_is_empty(): void
    {
        $this->actingAsReportViewer();
        $seller = Company::factory()->create(['name' => '', 'english_name' => 'Acme Seller Ltd']);
        $order = RegisteredOrder::factory()->create(['seller_id' => $seller->id]);
        $file = $this->customsFile($order);
        $doc = Status::create(['type' => Shipment::TYPE_DOC_STATUS, 'english_type' => Shipment::TYPE_DOC_STATUS, 'name' => '', 'english_name' => 'Docs Ready '.uniqid()]);
        $file->shipment->update(['doc_status_id' => $doc->id]);

        Livewire::test(CustomsReport::class)
            ->set('filters.registered_order_id', $order->id)
            ->assertTableColumnStateSet('registeredOrder.sellerCompany.name', 'Acme Seller Ltd', $file->id)
            ->assertTableColumnStateSet('doc_status', $doc->english_name, $file->id);
    }

    public function test_the_page_needs_the_customs_permission(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CustomsReport::class)->assertForbidden();
    }

    public function test_the_page_lists_a_customs_file_and_its_cards(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $file = $this->customsFile($order);

        Livewire::test(CustomsReport::class)
            ->set('filters.registered_order_id', $order->id)
            ->assertSuccessful()
            ->assertSee($file->custom_no)
            ->assertSee(__('resources/report/strings.customs.cards.open'));
    }

    public function test_searching_and_sorting_the_table_by_contract_number_does_not_hit_an_ambiguous_column(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $file = $this->customsFile($order);

        Livewire::test(CustomsReport::class)
            ->set('filters.registered_order_id', $order->id)
            ->searchTable((string) $file->contract_no)
            ->assertSuccessful()
            ->sortTable('contract_no')
            ->assertSuccessful();
    }

    public function test_the_page_embeds_both_charts_and_the_attention_table(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $this->customsFile($order);

        Livewire::withQueryParams(['filters' => ['registered_order_id' => $order->id]])->test(CustomsReport::class)
            ->assertSeeLivewire(CustomsStageChart::class)
            ->assertSeeLivewire(CustomsClearanceByMonthChart::class)
            ->assertSeeLivewire(CustomsAttentionWidget::class);
    }

    public function test_the_attention_section_is_hidden_when_the_report_has_no_issues(): void
    {
        $this->actingAsReportViewer();
        $clean = RegisteredOrder::factory()->create();

        Livewire::withQueryParams(['filters' => ['registered_order_id' => $clean->id]])->test(CustomsReport::class)
            ->assertSeeLivewire(CustomsStageChart::class)
            ->assertDontSeeLivewire(CustomsAttentionWidget::class);
    }

    public function test_the_banner_severity_event_narrows_the_attention_table_and_show_all_clears_it(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $this->customsFile($order, ['doc_submission_date' => '2026-01-10', 'clearance_date' => '2026-01-05']);
        $this->customsFile($order, ['doc_submission_date' => null]);
        $error = __('resources/report/strings.attention.checks.custom.cleared_before_arrival.expected');
        $warning = __('resources/report/strings.attention.checks.custom.missing_doc_submission_date.expected');

        $widget = Livewire::test(CustomsAttentionWidget::class, ['pageFilters' => ['registered_order_id' => $order->id]])
            ->assertSee($error)
            ->assertSee($warning);

        $widget->dispatch('report-severity', severity: 'warning')->assertSee($warning)->assertDontSee($error);
        $widget->dispatch('report-severity', severity: 'error')->assertSee($error)->assertDontSee($warning);
        $widget->dispatch('report-severity', severity: null)->assertSee($error)->assertSee($warning);
    }

    public function test_every_report_widget_is_registered_in_the_panel_and_gated(): void
    {
        $widgets = Filament::getPanel('dashboard')->getWidgets();

        foreach ([CustomsAttentionWidget::class, CustomsStageChart::class, CustomsClearanceByMonthChart::class] as $widget) {
            $this->assertContains($widget, $widgets);
        }

        $this->assertFalse(CustomsStageChart::canView());

        $this->actingAsReportViewer();

        $this->assertTrue(CustomsStageChart::canView());
        $this->assertTrue(CustomsClearanceByMonthChart::canView());
        $this->assertTrue(CustomsAttentionWidget::canView());
    }

    public function test_the_charts_render_for_the_filtered_order(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();

        Livewire::test(CustomsStageChart::class, ['pageFilters' => ['registered_order_id' => $order->id]])->assertSuccessful();
        Livewire::test(CustomsClearanceByMonthChart::class, ['pageFilters' => ['registered_order_id' => $order->id]])->assertSuccessful();
    }

    private function quickFixture(): array
    {
        $order = RegisteredOrder::factory()->create();
        $notReturned = Status::firstOrCreate(
            ['english_type' => Custom::TYPE_BANK_GUARANTEE_STATUS, 'english_name' => 'Not Returned'],
            ['type' => Custom::TYPE_BANK_GUARANTEE_STATUS, 'name' => 'Not Returned'],
        );

        return [
            'order' => $order,
            'open' => $this->customsFile($order),
            'bare' => $this->customsFile($order, ['clearance_status_id' => null]),
            'cleared' => $this->customsFile($order, ['clearance_status_id' => $this->clearanceStatus('Completed')->id, 'bank_guarantee_status_id' => $notReturned->id]),
        ];
    }

    public function test_clicking_a_stat_line_narrows_the_table_and_a_second_click_clears_it(): void
    {
        $this->actingAsReportViewer();
        ['order' => $order, 'open' => $open, 'bare' => $bare, 'cleared' => $cleared] = $this->quickFixture();

        $page = Livewire::test(CustomsReport::class)->set('filters.registered_order_id', $order->id)
            ->assertCanSeeTableRecords([$open, $bare])
            ->assertCanNotSeeTableRecords([$cleared])
            ->call('filterTableBy', 'no_status')
            ->assertCanSeeTableRecords([$bare])
            ->assertCanNotSeeTableRecords([$open, $cleared])
            ->assertSeeHtml("removeTableFilter('quick')")
            ->call('filterTableBy', 'no_status')
            ->assertCanSeeTableRecords([$open, $bare])
            ->assertDontSeeHtml("removeTableFilter('quick')");

        $page->call('filterTableBy', 'guarantees_cleared')
            ->assertCanSeeTableRecords([$cleared])
            ->assertCanNotSeeTableRecords([$open, $bare]);
    }

    public function test_the_query_string_applies_a_stat_filter_and_shows_the_chip(): void
    {
        $this->actingAsReportViewer();
        ['order' => $order, 'open' => $open, 'bare' => $bare, 'cleared' => $cleared] = $this->quickFixture();

        Livewire::withQueryParams(['filters' => ['registered_order_id' => $order->id], 'q' => 'guarantees_not_returned'])->test(CustomsReport::class)
            ->assertCanSeeTableRecords([$cleared])
            ->assertCanNotSeeTableRecords([$open, $bare])
            ->assertSee(__('resources/report/strings.quick.active', ['label' => __('resources/report/strings.customs.cards.guarantees').' — '.__('resources/report/strings.customs.lines.count')]));
    }

    public function test_the_stat_lines_render_as_clickable_rows(): void
    {
        $this->actingAsReportViewer();
        ['order' => $order] = $this->quickFixture();

        Livewire::test(CustomsReport::class)->set('filters.registered_order_id', $order->id)
            ->assertSeeHtml("filterTableBy('open_files')")
            ->assertSeeHtml("filterTableBy('no_status')")
            ->assertSeeHtml("filterTableBy('over_threshold')")
            ->assertSeeHtml("filterTableBy('guarantees_cleared')")
            ->assertSeeHtml("filterTableBy('stage_".$this->clearanceStatus('Declared')->id."')");
    }

    public function test_the_new_columns_are_hidden_by_default_and_the_aliases_are_sortable_and_searchable(): void
    {
        $this->actingAsReportViewer();
        $columns = Livewire::test(CustomsReport::class)->instance()->getTable()->getColumns();

        $this->assertTrue($columns['commitmentStatus.name']->isToggledHiddenByDefault());
        $this->assertTrue($columns['registeredOrder.sellerCompany.name']->isToggledHiddenByDefault());

        foreach (['products_text', 'total_weight', 'registeredOrder.sellerCompany.name'] as $name) {
            $this->assertTrue($columns[$name]->isSortable(), $name);
        }

        foreach (['products_text', 'registeredOrder.sellerCompany.name'] as $name) {
            $this->assertTrue($columns[$name]->isSearchable(), $name);
        }

        $this->assertSame(['clearance_status_id', 'seller'], array_keys(Livewire::test(CustomsReport::class)->instance()->getTable()->getGroups()));
    }

    public function test_searching_sorting_and_grouping_by_the_new_columns_work(): void
    {
        $this->actingAsReportViewer();
        $seller = Company::factory()->seller()->create(['english_name' => 'Seller CustomsPage', 'name' => 'Seller CustomsPage']);
        $order = RegisteredOrder::factory()->create(['seller_id' => $seller->id]);
        $heavy = $this->customsFile($order);
        $light = $this->customsFile($order);
        ShipmentItem::factory()->create(['shipment_id' => $heavy->shipment_id, 'product_id' => Product::factory()->create(['english_name' => 'Brass Rod ZQ', 'name' => 'Brass Rod ZQ'])->id, 'gross_weight' => 900]);
        ShipmentItem::factory()->create(['shipment_id' => $light->shipment_id, 'product_id' => Product::factory()->create()->id, 'gross_weight' => 50]);

        $page = Livewire::test(CustomsReport::class)->set('filters.registered_order_id', $order->id);

        $page->searchTable('Brass Rod ZQ')->assertCanSeeTableRecords([$heavy])->assertCanNotSeeTableRecords([$light]);
        $page->searchTable('Seller CustomsPage')->assertCanSeeTableRecords([$heavy, $light]);
        $page->searchTable('')->sortTable('total_weight')->assertCanSeeTableRecords([$light, $heavy], inOrder: true);
        $page->sortTable('total_weight', 'desc')->assertCanSeeTableRecords([$heavy, $light], inOrder: true);

        foreach (['products_text', 'registeredOrder.sellerCompany.name'] as $name) {
            $page->sortTable($name)->assertSuccessful();
        }

        foreach (['clearance_status_id', 'seller'] as $group) {
            Livewire::test(CustomsReport::class)->set('filters.registered_order_id', $order->id)->set('tableGrouping', $group)->assertSuccessful()->assertCanSeeTableRecords([$heavy, $light]);
        }
    }

    public function test_the_days_badge_follows_the_threshold_filter(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $this->customsFile($order);
        $color = function (array $filters) use ($order): string {
            $page = Livewire::test(CustomsReport::class)->set('filters.registered_order_id', $order->id);

            foreach ($filters as $key => $value) {
                $page->set("filters.{$key}", $value);
            }

            $record = app(CustomsReportService::class)->query($page->instance()->filterObject)->first();

            return $page->instance()->getTable()->getColumn('days_in_customs')->record($record)->getColor($record->days_in_customs);
        };

        $this->assertSame('success', $color([]));
        $this->assertSame('warning', $color(['stuck_days' => 20]));
        $this->assertSame('danger', $color(['stuck_days' => 9]));
    }

    public function test_the_filters_form_carries_the_new_basis_status_and_carrier_fields(): void
    {
        $this->actingAsReportViewer();
        $fields = array_keys(Livewire::test(CustomsReport::class)->instance()->getSchema('filtersForm')->getFlatFields());

        foreach (['date_basis', 'include_undated', 'doc_status_id', 'company_id', 'stuck_days', 'cleared_from_stage', 'arrival_basis'] as $field) {
            $this->assertContains($field, $fields);
        }
    }

    public function test_the_guarantee_card_shows_its_note_line_on_the_page(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();

        Livewire::test(CustomsReport::class)->set('filters.registered_order_id', $order->id)
            ->assertSee(__('resources/report/strings.customs.lines.guarantee_note'));
    }

    public function test_the_guide_is_complete_in_every_locale(): void
    {
        $this->actingAsReportViewer();
        $page = Livewire::test(CustomsReport::class)->instance();
        $filters = array_keys($page->getSchema('filtersForm')->getFlatFields());
        $columns = array_keys($page->getTable()->getColumns());
        $cards = array_column(app(CustomsReportService::class)->summary(new ReportFilters), 'key');
        $checks = app(CheckRegistry::class)->forReport('customs')->map(fn ($check): string => str_replace('.', '_', $check->key))->all();

        $this->assertCount(19, $filters);
        $this->assertCount(20, $columns);
        $this->assertCount(4, $cards);
        $this->assertNotEmpty($checks);

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $sections = __('resources/report/guide_customs.sections');

            $this->assertIsArray($sections);
            $this->assertNotSame('', __('resources/report/guide_customs.intro'));
            $this->assertSame(['filters', 'cards', 'charts', 'table', 'issues'], array_keys($sections), $locale);

            foreach ($sections as $name => $section) {
                $this->assertNotEmpty($section['title'] ?? null, "{$locale}.{$name}.title");
            }

            foreach ([
                'filters' => $filters,
                'cards' => $cards,
                'charts' => ['stage', 'clearance_by_month'],
                'table' => $columns,
                'issues' => $checks,
            ] as $name => $keys) {
                foreach ($keys as $key) {
                    $item = $sections[$name]['items'][$key] ?? null;

                    $this->assertIsArray($item, "{$locale}.{$name}.{$key}");
                    $this->assertNotEmpty($item['title'] ?? null, "{$locale}.{$name}.{$key}.title");
                    $this->assertNotEmpty($item['meaning'] ?? null, "{$locale}.{$name}.{$key}.meaning");
                }
            }

            foreach ($checks as $key) {
                foreach (['meaning', 'how', 'change'] as $part) {
                    $this->assertNotEmpty($sections['issues']['items'][$key][$part] ?? null, "{$locale}.issues.{$key}.{$part}");
                }
            }

            array_walk_recursive($sections, fn ($value) => $this->assertStringNotContainsString('TODO', (string) $value));
        }
    }
}
