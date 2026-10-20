<?php

namespace Tests\Feature\Filament\Reports;

use App\Filament\Pages\Reports\InTransit\InTransitAttentionWidget;
use App\Filament\Pages\Reports\InTransit\InTransitEtaChart;
use App\Filament\Pages\Reports\InTransit\InTransitReport;
use App\Filament\Pages\Reports\InTransit\InTransitStageChart;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Product;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Services\Reports\Builders\InTransitReportService;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Support\ReportFilters;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class InTransitReportPageTest extends TestCase
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
        $role = Role::create(['name' => 'in_transit_role_'.uniqid(), 'guard_name' => 'web']);

        foreach (['report.view_in_transit', 'shipment.view'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $user->assignRole($role);
        $this->actingAs($user);
    }

    private function shipment(RegisteredOrder $order, array $attributes = []): Shipment
    {
        return Shipment::factory()->create($attributes + [
            'registered_order_id' => $order->id,
            'warehouse_date' => null,
            'exit_date' => null,
            'eta' => today()->addDays(5)->toDateString(),
            'etd' => today()->subDays(3)->toDateString(),
            'shipment_status_id' => null,
        ]);
    }

    public function test_the_page_needs_the_in_transit_permission(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(InTransitReport::class)->assertForbidden();
    }

    public function test_the_eta_column_renders_in_the_active_calendar(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $this->shipment($order, ['exit_date' => '2025-03-01', 'eta' => '2025-03-20']);

        foreach (['gregorian' => toGregorianDate('2025-03-20'), 'jalali' => toPersianDate('2025-03-20')] as $calendar => $expected) {
            session(['calendar_type' => $calendar]);

            Livewire::test(InTransitReport::class)->set('filters.registered_order_id', $order->id)->set('filters.date_basis', 'eta')->assertSee($expected);
        }

        session()->forget('calendar_type');
        $this->assertNotSame(toGregorianDate('2025-03-20'), toPersianDate('2025-03-20'));
    }

    public function test_the_stage_chart_gives_every_slice_its_own_colour_for_any_number_of_statuses(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $chart = Livewire::test(InTransitStageChart::class, ['pageFilters' => ['registered_order_id' => $order->id]])->instance();
        $data = (fn () => $this->getData())->call($chart);
        $colors = $data['datasets'][0]['backgroundColor'];

        $this->assertCount(count($data['labels']), $colors);
        $this->assertNotContains(null, $colors);
        $this->assertSame($colors, array_values(array_filter($colors, 'is_string')));
    }

    public function test_the_page_lists_a_shipment_and_the_four_cards(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $shipment = $this->shipment($order, ['exit_date' => today()->toDateString()]);

        Livewire::test(InTransitReport::class)
            ->set('filters.registered_order_id', $order->id)
            ->assertSuccessful()
            ->assertSee($shipment->shipment_no)
            ->assertSee(__('resources/report/strings.in_transit.cards.awaiting'))
            ->assertSee(__('resources/report/strings.in_transit.cards.in_transit'))
            ->assertSee(__('resources/report/strings.in_transit.cards.arriving'))
            ->assertSee(__('resources/report/strings.in_transit.cards.delayed'));
    }

    public function test_every_widget_is_registered_gated_and_renders(): void
    {
        $widgets = Filament::getPanel('dashboard')->getWidgets();
        $classes = [InTransitStageChart::class, InTransitEtaChart::class, InTransitAttentionWidget::class];

        foreach ($classes as $widget) {
            $this->assertContains($widget, $widgets);
        }

        $this->assertFalse(InTransitStageChart::canView());

        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $this->shipment($order);

        foreach ($classes as $widget) {
            $this->assertTrue($widget::canView());
        }

        Livewire::test(InTransitStageChart::class, ['pageFilters' => ['registered_order_id' => $order->id]])->assertSuccessful();
        Livewire::test(InTransitEtaChart::class, ['pageFilters' => ['registered_order_id' => $order->id]])->assertSuccessful();
    }

    public function test_a_card_line_click_filters_the_table_to_the_counted_rows(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $awaiting = $this->shipment($order);
        $departed = $this->shipment($order, ['exit_date' => today()->toDateString()]);
        $service = app(InTransitReportService::class);
        $filters = ReportFilters::fromArray(['registered_order_id' => $order->id]);

        $this->assertSame('1', $service->summary($filters)[0]['lines'][0]['value']);

        Livewire::test(InTransitReport::class)->set('filters.registered_order_id', $order->id)
            ->assertCanSeeTableRecords([$awaiting, $departed])
            ->assertSeeHtml("filterTableBy('awaiting')")
            ->call('filterTableBy', 'awaiting')
            ->assertCanSeeTableRecords([$awaiting])
            ->assertCanNotSeeTableRecords([$departed])
            ->sortTable('days_to_eta')
            ->assertSuccessful();
    }

    public function test_the_new_columns_default_visibility_and_capabilities(): void
    {
        $this->actingAsReportViewer();
        $columns = Livewire::test(InTransitReport::class)->instance()->getTable()->getColumns();

        $this->assertTrue($columns['state']->isToggledHiddenByDefault());
        $this->assertFalse($columns['total_weight']->isToggledHiddenByDefault());
        $this->assertFalse($columns['registeredOrder.sellerCompany.name']->isToggledHiddenByDefault());
        $this->assertTrue($columns['registeredOrder.sellerCompany.name']->isToggleable());

        foreach (['state', 'total_weight', 'products_text', 'registeredOrder.sellerCompany.name'] as $name) {
            $this->assertTrue($columns[$name]->isSortable(), $name);
        }

        foreach (['state', 'products_text', 'registeredOrder.sellerCompany.name'] as $name) {
            $this->assertTrue($columns[$name]->isSearchable(), $name);
        }

        $this->assertSame(['company_id', 'seller'], array_keys(Livewire::test(InTransitReport::class)->instance()->getTable()->getGroups()));
    }

    public function test_searching_and_sorting_by_products_state_seller_and_weight_work_without_ambiguity(): void
    {
        $this->actingAsReportViewer();
        $seller = Company::factory()->seller()->create(['english_name' => 'Seller PageSearch', 'name' => 'Seller PageSearch']);
        $order = RegisteredOrder::factory()->create(['seller_id' => $seller->id]);
        $moving = $this->shipment($order, ['exit_date' => today()->toDateString()]);
        $waiting = $this->shipment($order);
        $zinc = Product::factory()->create(['english_name' => 'Zinc Ingot QX', 'name' => 'Zinc Ingot QX']);
        ShipmentItem::factory()->create(['shipment_id' => $moving->id, 'product_id' => $zinc->id, 'gross_weight' => 500]);
        ShipmentItem::factory()->create(['shipment_id' => $waiting->id, 'product_id' => Product::factory()->create()->id, 'gross_weight' => 100]);

        $page = Livewire::test(InTransitReport::class)->set('filters.registered_order_id', $order->id);

        $page->searchTable('Zinc Ingot QX')->assertCanSeeTableRecords([$moving])->assertCanNotSeeTableRecords([$waiting]);
        $page->searchTable('in transit')->assertCanSeeTableRecords([$moving])->assertCanNotSeeTableRecords([$waiting]);
        $page->searchTable('Seller PageSearch')->assertCanSeeTableRecords([$moving, $waiting]);
        $page->searchTable('')->sortTable('total_weight')->assertCanSeeTableRecords([$waiting, $moving], inOrder: true);
        $page->sortTable('total_weight', 'desc')->assertCanSeeTableRecords([$moving, $waiting], inOrder: true);

        foreach (['state', 'products_text', 'registeredOrder.sellerCompany.name'] as $name) {
            $page->sortTable($name)->assertSuccessful();
        }
    }

    public function test_grouping_by_carrier_and_by_seller_lists_the_rows(): void
    {
        $this->actingAsReportViewer();
        $seller = Company::factory()->seller()->create(['english_name' => 'Seller GroupX', 'name' => 'Seller GroupX']);
        $order = RegisteredOrder::factory()->create(['seller_id' => $seller->id]);
        $shipment = $this->shipment($order);

        foreach (['company_id', 'seller'] as $group) {
            Livewire::test(InTransitReport::class)
                ->set('filters.registered_order_id', $order->id)
                ->set('tableGrouping', $group)
                ->assertSuccessful()
                ->assertCanSeeTableRecords([$shipment]);
        }

        Livewire::test(InTransitReport::class)->set('filters.registered_order_id', $order->id)->set('tableGrouping', 'seller')->assertSee('Seller GroupX');
    }

    public function test_the_days_to_eta_badge_follows_the_first_arrival_window(): void
    {
        $this->actingAsReportViewer();
        $order = RegisteredOrder::factory()->create();
        $this->shipment($order, ['eta' => today()->addDays(5)->toDateString()]);
        $color = function (array $filters) use ($order): string {
            $page = Livewire::test(InTransitReport::class)->set('filters.registered_order_id', $order->id);

            foreach ($filters as $key => $value) {
                $page->set("filters.{$key}", $value);
            }

            $record = app(InTransitReportService::class)->query($page->instance()->filterObject)->first();

            return $page->instance()->getTable()->getColumn('days_to_eta')->record($record)->getColor($record->days_to_eta);
        };

        $this->assertSame('warning', $color([]));
        $this->assertSame('success', $color(['th_arrive_1' => 3]));
    }

    public function test_the_filters_form_carries_the_new_threshold_basis_and_toggle_fields(): void
    {
        $this->actingAsReportViewer();
        $fields = array_keys(Livewire::test(InTransitReport::class)->instance()->getSchema('filtersForm')->getFlatFields());

        foreach (['date_basis', 'include_undated', 'include_void', 'departure_basis', 'shipment_status_id', 'th_arrive_1', 'th_arrive_2', 'th_late_error', 'stuck_days'] as $field) {
            $this->assertContains($field, $fields);
        }
    }

    public function test_the_guide_is_complete_in_every_locale(): void
    {
        $this->actingAsReportViewer();
        $page = Livewire::test(InTransitReport::class)->instance();
        $filters = array_keys($page->getSchema('filtersForm')->getFlatFields());
        $columns = array_keys($page->getTable()->getColumns());
        $cards = array_column(app(InTransitReportService::class)->summary(new ReportFilters), 'key');
        $checks = app(CheckRegistry::class)->forReport('in_transit')->map(fn ($check): string => str_replace('.', '_', $check->key))->all();

        $this->assertCount(20, $filters);
        $this->assertCount(18, $columns);
        $this->assertSame(['awaiting', 'in_transit', 'arriving', 'delayed'], $cards);
        $this->assertCount(11, $checks);

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $sections = __('resources/report/guide_in_transit.sections');

            $this->assertIsArray($sections);
            $this->assertNotSame('', __('resources/report/guide_in_transit.intro'));
            $this->assertSame(['filters', 'cards', 'charts', 'table', 'issues'], array_keys($sections), $locale);

            foreach ($sections as $name => $section) {
                $this->assertNotEmpty($section['title'] ?? null, "{$locale}.{$name}.title");
            }

            foreach ([
                'filters' => $filters,
                'cards' => $cards,
                'charts' => ['stages', 'eta_buckets'],
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
        }
    }
}
