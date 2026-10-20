<?php

namespace Tests\Feature\Filament\Reports;

use App\Filament\Pages\Reports\Requester\RequesterAttentionWidget;
use App\Filament\Pages\Reports\Requester\RequesterLateByStageChart;
use App\Filament\Pages\Reports\Requester\RequesterReport;
use App\Filament\Pages\Reports\Requester\RequesterStageChart;
use App\Models\Custom;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Status;
use App\Models\User;
use App\Services\Reports\Builders\RequesterReportService;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Rules\RequesterStages;
use App\Services\Reports\Support\ReportFilters;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class RequesterReportPageTest extends TestCase
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

    private function actingAsViewer(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'requester_role_'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'report.view_requester', 'guard_name' => 'web']));
        $user->assignRole($role);
        $this->actingAs($user);
    }

    private function item(Department $department, array $request = []): PurchaseRequestItem
    {
        $pr = PurchaseRequest::factory()->create($request + ['cost_center_id' => $department->id]);

        return PurchaseRequestItem::factory()->create(['purchase_request_id' => $pr->id, 'product_id' => Product::factory()->create()->id]);
    }

    public function test_the_required_by_column_renders_in_the_active_calendar(): void
    {
        $this->actingAsViewer();
        $department = Department::factory()->create();
        $this->item($department, ['required_by_date' => '2025-03-20']);

        foreach (['gregorian' => toGregorianDate('2025-03-20'), 'jalali' => toPersianDate('2025-03-20')] as $calendar => $expected) {
            session(['calendar_type' => $calendar]);

            Livewire::test(RequesterReport::class)->set('filters.cost_center_id', $department->id)->assertSee($expected);
        }

        session()->forget('calendar_type');
    }

    private function clearedItem(Department $department): PurchaseRequestItem
    {
        $item = $this->item($department);
        $order = RegisteredOrder::factory()->create();
        $order->purchaseRequests()->attach($item->purchase_request_id);
        $shipment = Shipment::factory()->create(['registered_order_id' => $order->id, 'shipment_status_id' => null, 'exit_date' => null, 'eta' => null, 'warehouse_date' => today()->subDay()->toDateString()]);
        ShipmentItem::factory()->create(['shipment_id' => $shipment->id, 'product_id' => $item->product_id]);
        Custom::factory()->create(['shipment_id' => $shipment->id, 'registered_order_id' => $order->id, 'clearance_status_id' => Status::firstOrCreate(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'english_name' => 'Completed'], ['type' => Custom::TYPE_CLEARANCE_STATUS, 'name' => 'Completed'])->id]);
        ReportStages::flush();

        return $item;
    }

    public function test_finished_items_are_hidden_until_the_toggle_is_on_and_the_hidden_count_is_shown(): void
    {
        $this->actingAsViewer();
        $department = Department::factory()->create(['english_name' => 'Dept '.uniqid()]);
        $open = $this->item($department);
        $done = $this->clearedItem($department);
        $note = fn ($page): ?array => collect($page->instance()->summary[2]['lines'])->firstWhere('label', __('resources/report/strings.requester.lines.finished_hidden'));

        $page = Livewire::test(RequesterReport::class)->set('filters.cost_center_id', $department->id)
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$done])
            ->assertSee(__('resources/report/strings.requester.lines.finished_hidden'));

        $this->assertSame('1', $note($page)['value']);
        $this->assertArrayNotHasKey('quick', $note($page));

        $page->set('filters.show_closed', true)->assertCanSeeTableRecords([$open, $done]);

        $this->assertNull($note($page));

        $page->set('filters.show_closed', false)
            ->call('filterTableBy', 'stage_cleared')
            ->assertCanSeeTableRecords([$done])
            ->assertCanNotSeeTableRecords([$open]);
    }

    public function test_the_registered_order_filter_lists_order_numbers_only(): void
    {
        $this->actingAsViewer();
        $order = RegisteredOrder::factory()->create();
        $options = Livewire::test(RequesterReport::class)->instance()->getSchema('filtersForm')->getFlatFields()['registered_order_id']->getOptions();

        $this->assertSame($order->ro_number, $options[$order->id]);

        foreach ($options as $label) {
            $this->assertStringNotContainsString('=>', (string) $label);
            $this->assertStringNotContainsString('┆', (string) $label);
        }
    }

    public function test_the_filter_accordion_keeps_the_business_filters_on_top_and_the_thresholds_in_a_collapsed_advanced_section(): void
    {
        $this->actingAsViewer();
        $page = Livewire::test(RequesterReport::class)->instance();
        $sections = collect($page->getSchema('filtersForm')->getFlatComponents())->filter(fn ($component): bool => $component instanceof Section && (string) $component->getHeading() === __('resources/report/strings.filters.advanced'));

        $this->assertCount(1, $sections);
        $this->assertTrue($sections->first()->isCollapsed());
        $this->assertEqualsCanonicalizing(['cleared_from_stage', 'include_void', 'departure_basis', 'th_arrive_1', 'th_late_grace', 'th_supply'], array_keys($sections->first()->getChildSchema()->getFlatFields()));
        $this->assertSame(__('resources/report/strings.filters.show_finished'), (string) RequesterReport::getShowClosedField('show_finished')->getLabel());
    }

    public function test_the_reviewing_stage_is_labelled_with_the_purchase_request_status_name(): void
    {
        $this->actingAsViewer();
        $department = Department::factory()->create(['english_name' => 'Dept '.uniqid()]);
        $this->item($department);

        Livewire::test(RequesterReport::class)->set('filters.cost_center_id', $department->id)
            ->assertSee(RequesterStages::label('reviewing'));

        $this->assertNotSame(__('resources/purchaseRequest/strings.general.model_label'), RequesterStages::label('reviewing'));
        $this->assertSame('Under Review', RequesterStages::label('reviewing'));
    }

    public function test_the_requester_and_status_filters_narrow_the_table(): void
    {
        $this->actingAsViewer();
        $department = Department::factory()->create(['english_name' => 'Dept '.uniqid()]);
        $other = User::factory()->create();
        $authorized = Status::firstOrCreate(['english_type' => PurchaseRequest::TYPE_PURCHASE_REQUEST, 'english_name' => 'Authorized'], ['type' => PurchaseRequest::TYPE_PURCHASE_REQUEST, 'name' => 'Authorized']);
        $declined = Status::firstOrCreate(['english_type' => PurchaseRequest::TYPE_PURCHASE_REQUEST, 'english_name' => 'Declined'], ['type' => PurchaseRequest::TYPE_PURCHASE_REQUEST, 'name' => 'Declined']);
        $mine = $this->item($department, ['status_id' => $authorized->id]);
        $theirs = $this->item($department, ['requester_id' => $other->id, 'status_id' => $declined->id]);

        Livewire::test(RequesterReport::class)->set('filters.cost_center_id', $department->id)
            ->assertCanSeeTableRecords([$mine, $theirs])
            ->set('filters.requester_id', $other->id)
            ->assertCanSeeTableRecords([$theirs])
            ->assertCanNotSeeTableRecords([$mine])
            ->set('filters.requester_id', null)
            ->set('filters.status_id', [$authorized->id])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_the_page_needs_the_requester_permission(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(RequesterReport::class)->assertForbidden();
    }

    public function test_the_page_lists_items_of_every_department_and_the_cards(): void
    {
        $this->actingAsViewer();
        $department = Department::factory()->create(['english_name' => 'Dept '.uniqid()]);
        $first = $this->item($department);
        $second = $this->item($department, ['cost_center_id' => null]);

        Livewire::test(RequesterReport::class)
            ->set('filters.cost_center_id', $department->id)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$first])
            ->assertCanNotSeeTableRecords([$second])
            ->assertSee(__('resources/report/strings.requester.cards.not_shipped'));
    }

    public function test_a_stat_line_click_narrows_the_table_and_the_issues_log_is_open_to_every_viewer(): void
    {
        $this->actingAsViewer();
        $department = Department::factory()->create(['english_name' => 'Dept '.uniqid()]);
        $reviewing = $this->item($department);
        $sourcing = $this->item($department);
        RegisteredOrder::factory()->create()->purchaseRequests()->attach($sourcing->purchase_request_id);

        Livewire::test(RequesterReport::class)->set('filters.cost_center_id', $department->id)
            ->assertSeeHtml("filterTableBy('stage_sourcing')")
            ->call('filterTableBy', 'stage_sourcing')
            ->assertCanSeeTableRecords([$sourcing])
            ->assertCanNotSeeTableRecords([$reviewing]);

        $this->assertCount(9, app(CheckRegistry::class)->forReport('requester'));
        $this->assertTrue(RequesterAttentionWidget::canView());
    }

    public function test_every_report_widget_is_registered_in_the_panel_and_gated(): void
    {
        $widgets = Filament::getPanel('dashboard')->getWidgets();

        foreach ([RequesterAttentionWidget::class, RequesterStageChart::class, RequesterLateByStageChart::class] as $widget) {
            $this->assertContains($widget, $widgets);
        }

        $this->assertFalse(RequesterStageChart::canView());

        $this->actingAsViewer();

        $this->assertTrue(RequesterStageChart::canView());
        Livewire::test(RequesterStageChart::class)->assertSuccessful();
        Livewire::test(RequesterLateByStageChart::class)->assertSuccessful();
    }

    public function test_the_guide_is_complete_in_every_locale(): void
    {
        $this->actingAsViewer();
        $page = Livewire::test(RequesterReport::class)->instance();
        $filters = array_keys($page->getSchema('filtersForm')->getFlatFields());
        $columns = array_keys($page->getTable()->getColumns());
        $cards = array_column(app(RequesterReportService::class)->summary(new ReportFilters), 'key');
        $checks = app(CheckRegistry::class)->forReport('requester')->map(fn ($check): string => str_replace('.', '_', $check->key))->all();

        $this->assertEqualsCanonicalizing(['date_from', 'date_until', 'date_basis', 'include_undated', 'requester_id', 'cost_center_id', 'product_id', 'category_id', 'status_id', 'stage', 'registered_order_id', 'show_closed', 'cleared_from_stage', 'include_void', 'departure_basis', 'th_arrive_1', 'th_late_grace', 'th_supply'], $filters);
        $this->assertCount(15, $columns);
        $this->assertCount(4, $cards);
        $this->assertCount(9, $checks);

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $sections = __('resources/report/guide_requester.sections');

            $this->assertNotSame('', __('resources/report/guide_requester.intro'));
            $this->assertSame(['filters', 'cards', 'charts', 'table', 'issues'], array_keys($sections), $locale);

            foreach (['filters' => $filters, 'cards' => $cards, 'charts' => ['stage', 'late_by_stage'], 'table' => $columns, 'issues' => $checks] as $name => $keys) {
                $this->assertNotEmpty($sections[$name]['title'] ?? null, "{$locale}.{$name}.title");

                foreach ($keys as $key) {
                    foreach (['title', 'meaning', 'how', 'change'] as $part) {
                        $this->assertNotEmpty($sections[$name]['items'][$key][$part] ?? null, "{$locale}.{$name}.{$key}.{$part}");
                    }
                }
            }

            array_walk_recursive($sections, fn ($value) => $this->assertStringNotContainsString('TODO', (string) $value));
        }
    }
}
