<?php

namespace Tests\Feature\Filament\Reports;

use App\Filament\Pages\Reports\FxCommitments\FxCommitmentsAttentionWidget;
use App\Filament\Pages\Reports\FxCommitments\FxCommitmentsByBankChart;
use App\Filament\Pages\Reports\FxCommitments\FxCommitmentsByMonthChart;
use App\Filament\Pages\Reports\FxCommitments\FxCommitmentsReport;
use App\Jobs\ExportReport;
use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Currency;
use App\Models\Custom;
use App\Models\Permission;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Services\Reports\Builders\FxCommitmentsReportService;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Rules\FxRateService;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\ReportFilters;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class FxCommitmentsReportPageTest extends TestCase
{
    private const MODEL_VIEWS = ['bank_profile.view', 'custom.view', 'shipment.view', 'registered_order.view'];

    private Currency $usd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        app()->setLocale('en');
        $this->usd = Currency::factory()->create();

        foreach ([[BankProfile::TYPE_BANK_PROFILE, 'Purchased'], [Custom::TYPE_CLEARANCE_STATUS, 'Completed'], [Shipment::TYPE_SHIPMENT_STATUS, 'Active']] as [$type, $name]) {
            Status::firstOrCreate(['english_type' => $type, 'english_name' => $name], ['type' => $type, 'name' => $name]);
        }

        FxRateService::flush();
        ReportStages::flush();
        CheckRegistry::invalidate();
    }

    protected function tearDown(): void
    {
        session()->forget('calendar_type');
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

    private function actingAsViewer(array $extra = self::MODEL_VIEWS): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'fx_commitments_role_'.uniqid(), 'guard_name' => 'web']);

        foreach (['report.view_fx_commitments', ...$extra] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function profile(RegisteredOrder $order, float $equivalent, array $extra = []): BankProfile
    {
        return BankProfile::factory()->create($extra + [
            'registered_order_id' => $order->id,
            'bank_id' => null,
            'status_id' => Status::where(['english_type' => BankProfile::TYPE_BANK_PROFILE, 'english_name' => 'Purchased'])->first()->id,
            'requested_currency_id' => $this->usd->id,
            'requested_amount' => $equivalent,
            'purchased_currency_id' => $this->usd->id,
            'purchased_equivalent' => $equivalent,
            'commitment_payment_date' => null,
        ]);
    }

    private function order(): RegisteredOrder
    {
        return RegisteredOrder::factory()->create(['currency_id' => $this->usd->id]);
    }

    private function cleared(RegisteredOrder $order, float $grand): void
    {
        Custom::factory()->create([
            'registered_order_id' => $order->id,
            'shipment_id' => Shipment::factory()->create([
                'registered_order_id' => $order->id,
                'grand_total' => $grand,
                'shipped_quantity' => 10,
                'status_id' => Status::where(['english_type' => Shipment::TYPE_SHIPMENT_STATUS, 'english_name' => 'Active'])->first()->id,
            ])->id,
            'clearance_status_id' => Status::where(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'english_name' => 'Completed'])->first()->id,
            'bank_guarantee_status_id' => null,
        ]);
    }

    public function test_the_page_needs_the_fx_commitments_permission(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(FxCommitmentsReport::class)->assertForbidden();
    }

    public function test_the_page_lists_overdue_and_open_profiles_first_and_shows_four_cards(): void
    {
        $this->actingAsViewer();
        $order = $this->order();
        $open = $this->profile($order, 600, ['commitment_payment_date' => today()->addDays(20)->toDateString()]);
        $late = $this->profile($order, 400, ['commitment_payment_date' => today()->subDays(3)->toDateString()]);
        $done = $this->order();
        $resolved = $this->profile($done, 500);
        $this->cleared($done, 500);

        $page = Livewire::test(FxCommitmentsReport::class)
            ->set('filters.registered_order_id', $order->id)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$late, $open], inOrder: true);

        $this->assertCount(4, $page->instance()->summary);
        $this->assertSame(__('resources/report/strings.fx_commitments.cards.overdue'), $page->instance()->summary[3]['label']);

        $page
            ->set('filters.registered_order_id', $done->id)
            ->assertCanNotSeeTableRecords([$resolved])
            ->set('filters.commitment_state', 'resolved')
            ->assertCanSeeTableRecords([$resolved]);
    }

    public function test_clicking_a_line_narrows_the_table_and_the_chip_clears_it(): void
    {
        $this->actingAsViewer();
        $order = $this->order();
        $late = $this->profile($order, 400, ['commitment_payment_date' => today()->subDays(3)->toDateString()]);
        $open = $this->profile($order, 600, ['commitment_payment_date' => today()->addDays(60)->toDateString()]);

        Livewire::test(FxCommitmentsReport::class)
            ->set('filters.registered_order_id', $order->id)
            ->assertSeeHtml("filterTableBy('overdue')")
            ->assertSeeHtml("filterTableBy('profiles')")
            ->call('filterTableBy', 'overdue')
            ->assertCanSeeTableRecords([$late])
            ->assertCanNotSeeTableRecords([$open])
            ->assertSee(__('resources/report/strings.quick.active', ['label' => __('resources/report/strings.fx_commitments.cards.overdue').' — '.__('resources/report/strings.fx_commitments.lines.overdue')]))
            ->call('filterTableBy', 'overdue')
            ->assertCanSeeTableRecords([$late, $open]);
    }

    public function test_cards_keep_their_numbers_while_a_line_is_clicked(): void
    {
        $this->actingAsViewer();
        $order = $this->order();
        $this->profile($order, 400, ['commitment_payment_date' => today()->subDays(3)->toDateString()]);
        $page = Livewire::withQueryParams(['filters' => ['registered_order_id' => $order->id]])->test(FxCommitmentsReport::class);
        $before = $page->instance()->summary;

        $page->call('filterTableBy', 'overdue');

        $this->assertSame($before, $page->instance()->summary);
    }

    public function test_money_lines_are_not_clickable(): void
    {
        $this->actingAsViewer();
        $order = $this->order();
        $this->profile($order, 400);

        $html = Livewire::withQueryParams(['filters' => ['registered_order_id' => $order->id]])->test(FxCommitmentsReport::class)->html();

        $this->assertStringNotContainsString("filterTableBy('eur", $html);
        $this->assertStringNotContainsString("filterTableBy('unusable", $html);
    }

    public function test_the_table_can_be_grouped_and_the_totals_stay_per_currency(): void
    {
        $this->actingAsViewer();
        $order = $this->order();
        $bank = Bank::factory()->create();
        $profile = $this->profile($order, 400, ['bank_id' => $bank->id]);
        $page = Livewire::test(FxCommitmentsReport::class)->set('filters.registered_order_id', $order->id);

        $this->assertSame(['order', 'company', 'bank', 'currency'], array_keys($page->instance()->getTable()->getGroups()));

        foreach (['order', 'company', 'bank', 'currency'] as $group) {
            $page->set('tableGrouping', $group)->assertSuccessful()->assertCanSeeTableRecords([$profile]);
        }

        $page->set('tableGrouping', null)->assertSee($this->usd->getLocalizedNameAttribute().' 400.00');
    }

    public function test_dates_follow_the_calendar_in_the_table(): void
    {
        $this->actingAsViewer();
        $order = $this->order();
        $date = today()->addDays(9);
        $this->profile($order, 400, ['commitment_payment_date' => $date->toDateString()]);

        foreach (['gregorian', 'jalali'] as $calendar) {
            session(['calendar_type' => $calendar]);

            Livewire::test(FxCommitmentsReport::class)
                ->set('filters.registered_order_id', $order->id)
                ->assertSee(adaptiveDate($date));
        }
    }

    public function test_the_advanced_toggles_render_and_their_defaults_live_in_the_filter_object(): void
    {
        $this->actingAsViewer();
        $page = Livewire::test(FxCommitmentsReport::class);

        foreach (['advanced', 'include_flagged_invoices', 'include_outliers', 'fallback_order_amount', 'value_basis', 'rate_basis', 'amount_in', 'commitment_state', 'th_late_grace', 'th_commit_due', 'th_exceed_pct', 'th_outlier_factor'] as $key) {
            $page->assertSee(__("resources/report/strings.filters.{$key}"));
        }

        $filters = $page->instance()->filterObject;

        $this->assertSame(0, $filters->threshold('late_grace'));
        $this->assertSame(30, $filters->threshold('commit_due'));
        $this->assertSame('grand_total', $filters->valueBasis);
        $this->assertSame('purchased', $filters->amountIn);
        $this->assertSame('pair', $filters->rateBasis);
        $this->assertFalse($filters->fallbackOrderAmount);
    }

    public function test_the_page_embeds_both_charts_and_the_attention_table_only_when_there_are_issues(): void
    {
        $this->actingAsViewer();
        $broken = $this->order();
        $this->profile($broken, 400, ['purchased_currency_id' => null]);
        $clean = $this->order();
        $this->profile($clean, 400, ['commitment_payment_date' => today()->addDays(90)->toDateString()]);

        Livewire::withQueryParams(['filters' => ['registered_order_id' => $broken->id]])->test(FxCommitmentsReport::class)
            ->assertSeeLivewire(FxCommitmentsByMonthChart::class)
            ->assertSeeLivewire(FxCommitmentsByBankChart::class)
            ->assertSeeLivewire(FxCommitmentsAttentionWidget::class);

        Livewire::withQueryParams(['filters' => ['registered_order_id' => $clean->id]])->test(FxCommitmentsReport::class)
            ->assertDontSeeLivewire(FxCommitmentsAttentionWidget::class);
    }

    public function test_the_charts_and_the_attention_widget_render_for_the_filtered_order(): void
    {
        $this->actingAsViewer();
        $order = $this->order();
        $this->profile($order, 400, ['purchased_currency_id' => null]);
        $filters = ['registered_order_id' => $order->id];

        Livewire::test(FxCommitmentsByMonthChart::class, ['pageFilters' => $filters])->assertSuccessful();
        Livewire::test(FxCommitmentsByBankChart::class, ['pageFilters' => $filters])->assertSuccessful();
        Livewire::test(FxCommitmentsAttentionWidget::class, ['pageFilters' => $filters])
            ->assertSee(__('resources/report/strings.attention.checks.bank_profile.commitment_without_currency.expected'));
    }

    public function test_every_report_widget_is_registered_in_the_panel_and_gated(): void
    {
        $widgets = Filament::getPanel('dashboard')->getWidgets();

        foreach ([FxCommitmentsAttentionWidget::class, FxCommitmentsByMonthChart::class, FxCommitmentsByBankChart::class] as $widget) {
            $this->assertContains($widget, $widgets);
        }

        $this->assertFalse(FxCommitmentsByMonthChart::canView());

        $this->actingAsViewer();

        $this->assertTrue(FxCommitmentsByMonthChart::canView());
        $this->assertTrue(FxCommitmentsByBankChart::canView());
        $this->assertTrue(FxCommitmentsAttentionWidget::canView());
    }

    public function test_the_issues_log_only_lists_checks_of_modules_the_viewer_can_see(): void
    {
        $this->actingAsViewer([]);

        $this->assertCount(0, app(CheckRegistry::class)->forReport('fx_commitments'));

        $this->actingAsViewer(['bank_profile.view']);

        $this->assertSame(8, app(CheckRegistry::class)->forReport('fx_commitments')->count());

        $this->actingAsViewer(self::MODEL_VIEWS);

        $this->assertSame(21, app(CheckRegistry::class)->forReport('fx_commitments')->count());
    }

    public function test_the_export_action_queues_the_report_with_the_active_filters(): void
    {
        $this->actingAsViewer();
        Queue::fake();
        $order = $this->order();

        Livewire::test(FxCommitmentsReport::class)
            ->set('filters.registered_order_id', $order->id)
            ->callAction('exportCsv');

        Queue::assertPushed(ExportReport::class, fn (ExportReport $job): bool => $job->report === 'fx_commitments' && $job->filters['registered_order_id'] === $order->id);
    }

    public function test_the_month_chart_is_a_line_and_the_bank_chart_stays_a_bar(): void
    {
        $this->actingAsViewer();
        $type = fn (string $chart): string => (fn (): string => $this->getType())->call(new $chart);

        $this->assertSame('line', $type(FxCommitmentsByMonthChart::class));
        $this->assertSame('bar', $type(FxCommitmentsByBankChart::class));

        $widget = new FxCommitmentsByMonthChart;
        $widget->pageFilters = ['registered_order_id' => $this->order()->id];
        $data = (fn (): array => $this->getData())->call($widget);

        foreach ($data['datasets'] as $dataset) {
            $this->assertCount(count($data['labels']), $dataset['data']);
            $this->assertArrayHasKey('borderColor', $dataset);
        }
    }

    public function test_the_guide_covers_every_filter_card_chart_column_and_check_in_every_language(): void
    {
        $this->actingAsViewer();
        $order = $this->order();
        $filters = ReportFilters::fromArray(['registered_order_id' => $order->id]);
        $fields = ['date_from', 'date_until', 'date_basis', 'include_undated', 'bank_id', 'company_id', 'registered_order_id', 'purchased_currency_id', 'supply_source', 'status_id', 'commitment_state', 'commitment_status_id', 'bank_guarantee_status_id', 'cleared_from_stage', 'amount_in', 'th_late_grace', 'th_commit_due', 'include_flagged_invoices', 'include_outliers', 'fallback_order_amount', 'value_basis', 'rate_basis', 'include_void', 'th_exceed_pct', 'th_outlier_factor'];
        $columns = [...array_keys(FxCommitmentsReportService::EXPORT_COLUMNS), 'toggle', 'export'];
        $mounted = array_keys(Livewire::test(FxCommitmentsReport::class)->instance()->filtersForm->getFlatFields(withHidden: true));
        $this->assertEqualsCanonicalizing($fields, array_map(fn (string $path): string => last(explode('.', $path)), $mounted));
        $checks = app(CheckRegistry::class)->forReport('fx_commitments')->map(fn ($check): string => str_replace('.', '_', $check->key));
        $cards = collect(app(FxCommitmentsReportService::class)->summary($filters))->pluck('key');

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $guide = Livewire::test(FxCommitmentsReport::class)->instance()->guide;
            $this->assertNotSame('', $guide['intro']);
            $this->assertSame(['filters', 'cards', 'charts', 'table', 'issues'], array_keys($guide['sections']));

            $expected = ['filters' => $fields, 'cards' => $cards->all(), 'charts' => ['by_month', 'by_bank'], 'table' => $columns, 'issues' => $checks->all()];

            foreach ($expected as $section => $keys) {
                $this->assertNotEmpty($guide['sections'][$section]['title']);

                foreach ($keys as $key) {
                    $this->assertNotEmpty($guide['sections'][$section]['items'][$key]['title'] ?? null, "{$locale} {$section} {$key} title");
                }
            }

            foreach ([...$checks->all()] as $key) {
                foreach (['meaning', 'how', 'change'] as $part) {
                    $this->assertNotEmpty($guide['sections']['issues']['items'][$key][$part] ?? null, "{$locale} {$key} {$part}");
                }
            }

            array_walk_recursive($guide, fn ($text) => $this->assertDoesNotMatchRegularExpression('/TODO|:[a-z_]+\b/u', (string) $text));
        }
    }
}
