<?php

namespace Tests\Feature\Filament\Reports;

use App\Filament\Pages\Reports\Executive\ExecutiveAttentionWidget;
use App\Filament\Pages\Reports\Executive\ExecutiveEtaValueChart;
use App\Filament\Pages\Reports\Executive\ExecutiveReport;
use App\Filament\Pages\Reports\Executive\ExecutiveValueByCategoryChart;
use App\Filament\Pages\Reports\ReportPage;
use App\Models\Currency;
use App\Models\EntityAttribute;
use App\Models\Permission;
use App\Models\Product;
use App\Models\RegisteredOrder;
use App\Models\RegisteredOrderItem;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Status;
use App\Models\User;
use App\Services\Reports\Builders\ExecutiveReportService;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Rules\FxRateService;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Support\TransitLedger;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ExecutiveReportPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        app()->setLocale('en');
        Currency::whereRaw('LOWER(english_name) = ?', ['euro'])->delete();
        FxRateService::flush();
        TransitLedger::flush();
    }

    protected function tearDown(): void
    {
        session()->forget('calendar_type');
        TransitLedger::flush();
        DB::rollBack();
        FxRateService::flush();
        app()->setLocale('en');
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

    private function actingAsViewer(array $extra = []): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'executive_role_'.uniqid(), 'guard_name' => 'web']);

        foreach (['report.view_executive', 'shipment.view', 'registered_order.view', 'bank_profile.view', ...$extra] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $user->assignRole($role);
        $this->actingAs($user);
    }

    private function fixture(array $shipment = []): array
    {
        $eur = Currency::factory()->create(['english_name' => 'Euro']);
        $order = RegisteredOrder::factory()->create(['currency_id' => $eur->id]);
        $product = Product::factory()->create(['english_name' => 'Zinc Ingot XQ']);
        RegisteredOrderItem::factory()->create(['registered_order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 100, 'unit' => 'pcs', 'unit_price' => 10, 'line_total' => 1000]);
        $record = Shipment::factory()->create($shipment + [
            'registered_order_id' => $order->id,
            'shipment_status_id' => null,
            'status_id' => Status::firstOrCreate(['english_type' => Shipment::TYPE_SHIPMENT_STATUS, 'english_name' => 'Active Test Status'], ['type' => Shipment::TYPE_SHIPMENT_STATUS, 'name' => 'Active Test Status'])->id,
            'doc_status_id' => null,
            'warehouse_date' => null,
            'exit_date' => today()->subDays(5)->toDateString(),
            'etd' => null,
            'eta' => today()->addDays(10)->toDateString(),
            'grand_total' => 500,
        ]);
        EntityAttribute::factory()->forEntity($record)->create(['key' => 'commercial_invoice', 'value' => ['currency' => 'Euro']]);
        ShipmentItem::factory()->create(['shipment_id' => $record->id, 'product_id' => $product->id, 'english_name' => 'Zinc Ingot XQ', 'total_amount' => 500, 'gross_weight' => 50]);
        TransitLedger::flush();

        return [$order, $record, $product];
    }

    private function columns(object $page): array
    {
        return array_keys($page->instance()->getTable()->getColumns());
    }

    public function test_the_page_needs_the_executive_permission(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ExecutiveReport::class)->assertForbidden();
    }

    public function test_the_page_lists_a_shipment_with_four_cards_and_the_mode_switch(): void
    {
        $this->actingAsViewer();
        [$order, $shipment] = $this->fixture();

        $page = Livewire::test(ExecutiveReport::class)
            ->set('filters.registered_order_id', $order->id)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$shipment])
            ->assertSee($shipment->shipment_no)
            ->assertSee(__('resources/report/strings.executive.cards.cargo'))
            ->assertSee(__('resources/report/strings.executive.cards.value'))
            ->assertSee(__('resources/report/strings.executive.cards.cover'))
            ->assertSee(__('resources/report/strings.executive.cards.suppliers'))
            ->assertSee(__('resources/report/strings.executive.mode.product'))
            ->assertSeeHtml("filterTableBy('counted')");

        $this->assertContains('led_eur', $this->columns($page));
        $this->assertNotContains('category', $this->columns($page));

        $page->call('setViewMode', 'product')
            ->assertSet('viewMode', 'product')
            ->assertSee(__('resources/report/strings.executive.columns.charges'))
            ->assertSee(__('resources/report/strings.executive.mode.shipment'));

        $this->assertSame(['Zinc Ingot XQ', __('resources/report/strings.executive.columns.charges')], $page->instance()->getTableRecords()->pluck('product')->all());

        $this->assertContains('category', $this->columns($page));
        $this->assertNotContains('led_eur', $this->columns($page));

        $page->call('setViewMode', 'shipment')->assertCanSeeTableRecords([$shipment]);
        $this->assertContains('led_eur', $this->columns($page));
    }

    public function test_the_mode_rides_in_the_url_and_a_forged_mode_falls_back_to_shipments(): void
    {
        $this->actingAsViewer();
        [$order] = $this->fixture();

        $page = Livewire::withQueryParams(['mode' => 'product'])->test(ExecutiveReport::class)->set('filters.registered_order_id', $order->id);
        $this->assertContains('category', $this->columns($page));
        $this->assertSame('product', $page->instance()->filterObject->viewMode);

        $forged = Livewire::test(ExecutiveReport::class)->call('setViewMode', 'bogus');
        $this->assertSame('shipment', $forged->get('viewMode'));
        $this->assertSame('shipment', $forged->instance()->filterObject->viewMode);
    }

    public function test_the_quick_chip_forces_the_shipment_view_and_can_be_removed(): void
    {
        $this->actingAsViewer();
        [$order, $shipment] = $this->fixture();

        $page = Livewire::test(ExecutiveReport::class)
            ->set('filters.registered_order_id', $order->id)
            ->call('setViewMode', 'product')
            ->call('filterTableBy', 'counted')
            ->assertSet('viewMode', 'shipment')
            ->assertSet('quick', 'counted')
            ->assertCanSeeTableRecords([$shipment])
            ->assertSee(__('resources/report/strings.quick.active', ['label' => __('resources/report/strings.executive.cards.value').' — '.__('resources/report/strings.executive.lines.counted')]));

        $this->assertContains('led_eur', $this->columns($page));

        $page->call('removeTableFilter', 'quick')->assertSet('quick', null);
        $page->call('filterTableBy', 'excluded_data')->assertCanNotSeeTableRecords([$shipment]);
        $page->call('setViewMode', 'product')->assertSet('quick', null);
    }

    public function test_searching_and_sorting_work_in_both_views(): void
    {
        $this->actingAsViewer();
        [$order, $shipment] = $this->fixture();

        $page = Livewire::test(ExecutiveReport::class)->set('filters.registered_order_id', $order->id);

        $page->searchTable($shipment->shipment_no)->assertCanSeeTableRecords([$shipment]);
        $page->searchTable('Zinc Ingot XQ')->assertCanSeeTableRecords([$shipment]);
        $page->searchTable('no such thing')->assertCanNotSeeTableRecords([$shipment]);
        $page->searchTable('');

        foreach (['led_eur', 'led_value', 'source', 'supply', 'supplied_percent', 'led_weight', 'eta', 'days_to_eta', 'products_text', 'registeredOrder.sellerCompany.name'] as $column) {
            $page->sortTable($column)->sortTable($column, 'desc')->assertSuccessful();
        }

        $products = fn (): array => $page->instance()->getTableRecords()->pluck('product')->all();

        $page->call('setViewMode', 'product')->searchTable('Zinc');
        $this->assertContains('Zinc Ingot XQ', $products());
        $page->searchTable('absent product');
        $this->assertNotContains('Zinc Ingot XQ', $products());
        $page->searchTable('');
        $this->assertContains('Zinc Ingot XQ', $products());

        foreach (['category', 'product', 'quantity', 'net_weight', 'gross_weight', 'eur', 'shipments', 'eta', 'supply'] as $column) {
            $page->sortTable($column)->sortTable($column, 'desc')->assertSuccessful();
        }
    }

    public function test_the_eta_column_renders_in_the_active_calendar(): void
    {
        $this->actingAsViewer();
        [$order] = $this->fixture(['exit_date' => '2025-03-01', 'eta' => '2025-03-20']);

        foreach (['gregorian' => toGregorianDate('2025-03-20'), 'jalali' => toPersianDate('2025-03-20')] as $calendar => $expected) {
            session(['calendar_type' => $calendar]);

            Livewire::test(ExecutiveReport::class)->set('filters.registered_order_id', $order->id)->assertSee($expected);
        }

        $this->assertNotSame(toGregorianDate('2025-03-20'), toPersianDate('2025-03-20'));
    }

    public function test_the_product_view_eta_follows_the_calendar_and_iso_leap_day_round_trips(): void
    {
        $this->actingAsViewer();
        [$order] = $this->fixture(['exit_date' => '2025-03-01', 'eta' => '2025-03-20']);

        session(['calendar_type' => 'jalali']);
        Livewire::test(ExecutiveReport::class)->set('filters.registered_order_id', $order->id)->call('setViewMode', 'product')->assertSee(toPersianDate('2025-03-20'));

        $filters = ReportFilters::fromArray(['date_from' => '2025-03-20']);
        $this->assertSame('2025-03-20', $filters->toArray()['date_from']);
        $this->assertNotSame(ReportFilters::fromArray([])->cacheKey(), $filters->cacheKey());
    }

    public function test_every_widget_is_registered_gated_and_renders(): void
    {
        $widgets = Filament::getPanel('dashboard')->getWidgets();
        $classes = [ExecutiveValueByCategoryChart::class, ExecutiveEtaValueChart::class, ExecutiveAttentionWidget::class];

        foreach ($classes as $widget) {
            $this->assertContains($widget, $widgets);
        }

        $this->assertFalse(ExecutiveValueByCategoryChart::canView());

        $this->actingAsViewer();
        [$order] = $this->fixture();

        foreach ($classes as $widget) {
            $this->assertTrue($widget::canView());
        }

        Livewire::test(ExecutiveValueByCategoryChart::class, ['pageFilters' => ['registered_order_id' => $order->id]])->assertSuccessful();
        Livewire::test(ExecutiveEtaValueChart::class, ['pageFilters' => ['registered_order_id' => $order->id]])->assertSuccessful()->assertSee(__('resources/report/strings.executive.charts.by_eta.heading'));
    }

    public function test_the_page_is_registered_with_the_first_navigation_slot(): void
    {
        $this->assertSame(ExecutiveReport::class, ReportPage::registry()['executive']);
        $this->assertSame(1, (new \ReflectionClass(ExecutiveReport::class))->getProperty('navigationSort')->getDefaultValue());
        $this->assertSame(ExecutiveReportService::class, ExecutiveReport::service());
    }

    public function test_drill_down_urls_carry_the_right_filters_and_respect_permissions(): void
    {
        [$order] = $this->fixture();
        $filters = ReportFilters::fromArray(['registered_order_id' => $order->id, 'quick' => 'delayed', 'view_mode' => 'product', 'weight_basis' => 'net']);
        $service = app(ExecutiveReportService::class);

        $this->actingAsViewer();
        $cargo = collect($service->summary($filters)[0]['lines']);
        $this->assertArrayNotHasKey('url', $cargo[1]);

        $this->actingAsViewer(['report.view_in_transit']);
        $url = urldecode($service->summary($filters)[0]['lines'][1]['url']);
        $this->assertStringContainsString('q=in_transit_delayed', $url);
        $this->assertStringContainsString('filters[registered_order_id]='.$order->id, $url);
        $this->assertStringNotContainsString('weight_basis', $url);
        $this->assertStringNotContainsString('view_mode', $url);

        $unknown = fn (): array => collect($service->summary($filters)[2]['lines'])->firstWhere('quick', 'supply_unknown');

        if (isset(ReportPage::registry()['fx_commitments'])) {
            $this->actingAsViewer();
            $this->assertArrayNotHasKey('url', $unknown());
            $this->actingAsViewer(['report.view_fx_commitments']);
            $this->assertStringContainsString('commitment_state', urldecode($unknown()['url']));
            $this->assertStringNotContainsString('registered_order_id', urldecode($unknown()['url']));
        } else {
            $this->assertArrayNotHasKey('url', $unknown());
        }
    }

    public function test_the_filters_form_carries_the_new_fields(): void
    {
        $this->actingAsViewer();
        $fields = array_keys(Livewire::test(ExecutiveReport::class)->instance()->getSchema('filtersForm')->getFlatFields());

        foreach (['include_flagged_invoices', 'include_outliers', 'weight_basis', 'departure_basis', 'include_void', 'th_outlier_factor', 'th_supply_full', 'th_top_n', 'date_basis'] as $field) {
            $this->assertContains($field, $fields);
        }
    }

    public function test_the_table_page_costs_the_same_number_of_queries_for_few_and_many_rows(): void
    {
        $this->actingAsViewer();
        $count = function (int $rows): int {
            for ($i = 0; $i < $rows; $i++) {
                $this->fixture();
            }

            TransitLedger::flush();
            FxRateService::flush();
            DB::enableQueryLog();
            DB::flushQueryLog();
            Livewire::test(ExecutiveReport::class)->assertSuccessful();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $count(0);
        $small = $count(3);

        $this->assertSame($small, $count(20));
    }

    public function test_the_eta_chart_is_a_single_ring_doughnut_with_one_colour_per_slice(): void
    {
        $this->actingAsViewer();
        [$order] = $this->fixture();
        $widget = new ExecutiveEtaValueChart;
        $widget->pageFilters = ['registered_order_id' => $order->id];
        $type = (fn (): string => $this->getType())->call($widget);
        $data = (fn (): array => $this->getData())->call($widget);

        $this->assertSame('doughnut', $type);
        $this->assertCount(1, $data['datasets']);
        $this->assertCount(5, $data['labels']);
        $this->assertCount(5, $data['datasets'][0]['data']);
        $this->assertCount(5, $data['datasets'][0]['backgroundColor']);
        $this->assertEqualsWithDelta(500.0, array_sum($data['datasets'][0]['data']), 0.005);
    }

    public function test_the_guide_is_complete_in_every_locale(): void
    {
        $this->actingAsViewer();
        [$order] = $this->fixture();
        $page = Livewire::test(ExecutiveReport::class)->set('filters.registered_order_id', $order->id);
        $filters = array_keys($page->instance()->getSchema('filtersForm')->getFlatFields());
        $columns = collect([
            ...$this->columns($page),
            ...$this->columns($page->call('setViewMode', 'product')),
        ])->unique()->values()->all();
        $cards = array_column(app(ExecutiveReportService::class)->summary(new ReportFilters), 'key');
        $checks = app(CheckRegistry::class)->forReport('executive')->map(fn ($check): string => str_replace('.', '_', $check->key))->all();

        $this->assertCount(23, $filters);
        $this->assertCount(25, $columns);
        $this->assertSame(['cargo', 'value', 'cover', 'suppliers'], $cards);
        $this->assertCount(14, $checks);

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $sections = __('resources/report/guide_executive.sections');

            $this->assertIsArray($sections);
            $this->assertNotSame('', __('resources/report/guide_executive.intro'));
            $this->assertSame(['filters', 'cards', 'charts', 'table', 'issues'], array_keys($sections), $locale);

            foreach ($sections as $name => $section) {
                $this->assertNotEmpty($section['title'] ?? null, "{$locale}.{$name}.title");
            }

            foreach ([
                'filters' => $filters,
                'cards' => $cards,
                'charts' => ['by_category', 'by_eta'],
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

    public function test_every_lang_key_of_the_report_exists_in_every_locale(): void
    {
        $keys = ['cards', 'lines', 'columns', 'supply', 'source', 'reasons', 'flags', 'mode', 'charts'];

        foreach (['en', 'fa', 'fr'] as $locale) {
            $strings = trans('resources/report/strings.executive', [], $locale);

            foreach ($keys as $key) {
                $this->assertNotEmpty($strings[$key] ?? null, "{$locale}.{$key}");
            }

            foreach (app(ExecutiveReportService::class)->exportLabelsFor(new ReportFilters(viewMode: 'product')) as $label) {
                $this->assertNotSame('', trim($label));
            }

            foreach (['transit_value_unknown', 'transit_currency_unknown', 'transit_invoice_currency_mismatch', 'transit_invoice_total_outlier', 'transit_value_estimated', 'transit_fx_rate_missing'] as $check) {
                $this->assertNotEmpty(trans("resources/report/strings.attention.checks.shipment.{$check}.message", [], $locale));
            }
        }
    }
}
