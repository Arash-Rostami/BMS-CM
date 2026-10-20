<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CalendarDayWidget;
use App\Filament\Widgets\CalendarGridWidget;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Calendar\CalendarActivity;
use App\Services\Calendar\CalendarSeen;
use App\Services\Calendar\Display\CalendarBoard;
use App\Services\Calendar\Display\CalendarRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

class CalendarGridWidgetTest extends TestCase
{
    private array $guideKeys = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        DB::table('calendar_hits')->delete();
        DB::table('calendar_rules')->delete();
        app()->setLocale('en');

        Carbon::setTestNow(Carbon::parse('2026-10-08'));
        CalendarHit::flushVisibleRuleIds();
        CalendarSeen::flush();
        Queue::fake(); // observers are live: never let a dispatch run inline under QUEUE_CONNECTION=sync
    }

    protected function tearDown(): void
    {
        foreach ($this->guideKeys as $guideKey) {
            Cache::forget($guideKey);
        }
        CalendarHit::flushVisibleRuleIds();
        CalendarSeen::flush();
        Carbon::setTestNow();
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

    private function actingAsUserWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create();

        $role = Role::create(['name' => 'calendar_grid_test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    // UserStamps stamps auth()->id() on create, so a foreign-owned rule must be
    // created while acting as its owner, then control handed back.
    private function rule(User $owner, array $overrides = []): CalendarRule
    {
        $acting = auth()->user();
        $this->actingAs($owner);
        $rule = CalendarRule::factory()->create(array_merge(['user_id' => $owner->id], $overrides));
        $this->actingAs($acting);

        return $rule;
    }

    private int $nextSubjectId = 1;

    private function hit(CalendarRule $rule, string $eventDate, array $overrides = []): CalendarHit
    {
        return CalendarHit::factory()->create(array_merge([
            'calendar_rule_id' => $rule->id,
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $this->nextSubjectId++,
            'event_date' => $eventDate,
        ], $overrides));
    }

    private function cell(array $cells, string $iso): array
    {
        return collect($cells)->firstWhere('iso', $iso);
    }

    public function test_month_groups_come_back_as_grouped_counts_matching_the_unfiltered_total(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $range = CalendarRange::forMonth('2026-10-08', false);
        $sky = $this->rule($user, ['name' => 'sky rule', 'color' => 'sky']);
        $amber = $this->rule($user, ['name' => 'amber rule', 'color' => 'amber']);
        $this->hit($sky, '2026-10-15');
        $this->hit($sky, '2026-10-15');
        $this->hit($amber, '2026-10-15');
        $this->hit($amber, '2026-10-16');

        $groups = CalendarBoard::monthGroups($user, $range, null, null);
        $total = CalendarHit::query()
            ->visibleTo($user)
            ->whereBetween('event_date', [$range->start->toDateString(), $range->end->toDateString()])
            ->count();

        $this->assertSame(3, $groups->count());
        $skyGroup = $groups->filter(fn (CalendarHit $row): bool => $row->event_date->toDateString() === '2026-10-15' && $row->rule_color === 'sky')->first();
        $this->assertSame(2, (int) $skyGroup?->hit_count);
        $this->assertSame($total, (int) $groups->sum('hit_count'));

        $cells = Livewire::test(CalendarGridWidget::class)->instance()->cells;
        $this->assertSame(3, $this->cell($cells, '2026-10-15')['count']);
        $this->assertSame(['sky', 'amber'], $this->cell($cells, '2026-10-15')['colors']);
        $this->assertSame(1, $this->cell($cells, '2026-10-16')['count']);
        $this->assertSame(31, count($cells));
    }

    public function test_cells_mark_today_and_the_deep_linked_selection(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $cells = Livewire::withQueryParams(['cal_date' => '2026-10-15'])->test(CalendarGridWidget::class)->instance()->cells;

        $this->assertTrue($this->cell($cells, '2026-10-08')['isToday']);
        $this->assertTrue($this->cell($cells, '2026-10-15')['isSelected']);
        $this->assertFalse($this->cell($cells, '2026-10-15')['isToday']);
        $this->assertTrue($this->cell($cells, '2026-10-07')['isPast']);
    }

    public function test_past_heads_up_days_stay_empty_while_past_action_days_flag_overdue(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $headsUp = $this->rule($user);
        $action = $this->rule($user, ['type' => 'action']);
        $this->hit($headsUp, '2026-10-02');
        $this->hit($action, '2026-10-05');

        $cells = Livewire::test(CalendarGridWidget::class)->instance()->cells;

        $this->assertSame(0, $this->cell($cells, '2026-10-02')['count']);
        $this->assertSame(1, $this->cell($cells, '2026-10-05')['count']);
        $this->assertTrue($this->cell($cells, '2026-10-05')['hasOverdue']);
        $this->assertFalse($this->cell($cells, '2026-10-02')['hasOverdue']);
    }

    public function test_deep_link_params_are_kept_when_valid_and_dropped_when_forged(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $mine = $this->rule($user);
        $foreign = $this->rule(User::factory()->create(), ['visibility' => 'me']);

        $forged = Livewire::withQueryParams([
            'cal_date' => '2026-10-15',
            'cal_rule' => (string) $foreign->id,
            'cal_module' => Shipment::class,
        ])->test(CalendarGridWidget::class);

        $this->assertSame('2026-10-15', $forged->get('selected'));
        $this->assertNull($forged->get('ruleId'));
        $this->assertNull($forged->get('module'));

        $valid = Livewire::withQueryParams([
            'cal_date' => '2026-10-15',
            'cal_rule' => (string) $mine->id,
            'cal_module' => PurchaseRequest::class,
        ])->test(CalendarGridWidget::class);

        $this->assertSame($mine->id, $valid->get('ruleId'));
        $this->assertSame(PurchaseRequest::class, $valid->get('module'));
    }

    public function test_selecting_a_day_dispatches_the_sync_event(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        Livewire::test(CalendarGridWidget::class)
            ->call('selectDate', '2026-10-15')
            ->assertDispatched('calendar-day-selected', date: '2026-10-15', ruleId: null, module: null)
            ->assertSet('anchor', '2026-10-15');
    }

    public function test_the_rule_filter_narrows_cells_and_keeps_only_visible_rules_in_the_legend(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $mine = $this->rule($user, ['name' => 'kept rule']);
        $other = $this->rule($user, ['name' => 'filtered out rule']);
        $hidden = $this->rule(User::factory()->create(), ['name' => 'hidden rule', 'visibility' => 'me']);
        $this->hit($mine, '2026-10-15');
        $this->hit($other, '2026-10-15');

        $component = Livewire::test(CalendarGridWidget::class);
        $component->set('ruleId', $mine->id);

        $this->assertSame(1, $this->cell($component->instance()->cells, '2026-10-15')['count']);
        $component->assertDispatched('calendar-day-selected', date: '2026-10-08', ruleId: $mine->id, module: null)
            ->assertSee('kept rule')
            ->assertSee('filtered out rule')
            ->assertDontSee('hidden rule');
    }

    public function test_clicking_a_legend_chip_filters_the_calendar_and_clicking_it_again_clears_it(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $mine = $this->rule($user, ['name' => 'alpha rule']);
        $other = $this->rule($user, ['name' => 'beta rule']);
        $foreign = $this->rule(User::factory()->create(), ['visibility' => 'me']);
        $this->hit($mine, '2026-10-15');
        $this->hit($other, '2026-10-16');

        $component = Livewire::test(CalendarGridWidget::class);

        $component->call('toggleRule', $foreign->id)
            ->assertSet('ruleId', null);

        $component->call('toggleRule', $mine->id)
            ->assertSet('ruleId', $mine->id)
            ->assertDispatched('calendar-day-selected', date: '2026-10-08', ruleId: $mine->id, module: null)
            ->assertSeeHtml('wire:click="toggleRule('.$mine->id.')"');

        $this->assertSame(1, $this->cell($component->instance()->cells, '2026-10-15')['count']);
        $this->assertSame(0, $this->cell($component->instance()->cells, '2026-10-16')['count']);

        $component->call('toggleRule', $mine->id)
            ->assertSet('ruleId', null);
        $this->assertSame(1, $this->cell($component->instance()->cells, '2026-10-16')['count']);
    }

    public function test_selecting_a_rule_fills_the_guide_with_its_details_and_clearing_it_removes_them(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $mine = $this->rule($user, ['name' => 'detail rule', 'lead_times' => [7, 3]]);
        $foreign = $this->rule(User::factory()->create(), ['name' => 'secret detail rule', 'visibility' => 'me']);
        app(CalendarActivity::class)->log('rule_updated', $mine, $mine, ['active' => true]);

        $generic = __('resources/dashboard/strings.widgets.legend.calendar.what');
        $component = Livewire::test(CalendarGridWidget::class)->assertDontSeeHtml('cal-rule-head')->assertSee($generic);

        $component->call('toggleRule', $mine->id)
            ->assertSeeHtml('cal-rule-head')
            ->assertDontSee($generic)
            ->assertSee('detail rule')
            ->assertSee('was edited')
            ->assertSee('7, 3');

        $component->call('toggleRule', $foreign->id)
            ->assertDontSee('secret detail rule')
            ->assertDontSeeHtml('cal-rule-head')
            ->assertSee($generic);
        $this->assertNull($component->instance()->ruleDetails);
        $this->assertNull(CalendarBoard::ruleDetails($user, $foreign->id));

        $component->call('toggleRule', $mine->id)->call('toggleRule', $mine->id)
            ->assertDontSeeHtml('cal-rule-head');
    }

    public function test_the_legend_pages_rules_natively_and_always_keeps_the_selected_one(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        foreach (range(10, 29) as $n) {
            $this->rule($user, ['name' => 'rule '.$n]);
        }
        $last = $this->rule($user, ['name' => 'zz last rule']);
        $step = CalendarGridWidget::LEGEND_PER_PAGE;

        $component = Livewire::test(CalendarGridWidget::class);
        $this->assertCount($step, $component->instance()->legend);
        $this->assertSame(21, $component->instance()->legendTotal);
        $component->assertSeeHtml('fi-pagination');
        $this->assertNotContains('zz last rule', $component->instance()->legend->pluck('name')->all());

        $component->call('gotoPage', 2, 'legendPage');
        $this->assertContains('zz last rule', $component->instance()->legend->pluck('name')->all());
        $this->assertCount(21 - $step, $component->instance()->legend);

        $component->call('gotoPage', 99, 'legendPage');
        $this->assertCount(21 - $step, $component->instance()->legend);

        $component->call('gotoPage', 1, 'legendPage')->call('toggleRule', $last->id);
        $this->assertCount($step + 1, $component->instance()->legend);
        $this->assertContains('zz last rule', $component->instance()->legend->pluck('name')->all());
        $component->assertSeeHtml('cal-chip-clear');
    }

    public function test_the_month_shown_follows_today_and_the_jump_popover_preselects_the_new_month(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $component = Livewire::test(CalendarGridWidget::class)->call('jump', 2027, 3);
        $this->assertStringContainsString('year: 2027, month: 3', $component->html());
        $this->assertSame('March 2027', $component->instance()->rangeLabel);

        $component = Livewire::test(CalendarGridWidget::class)->call('nextMonth')->call('goToToday');
        $this->assertSame('October 2026', $component->instance()->rangeLabel);
        $this->assertStringContainsString('year: 2026, month: 10', $component->html());
    }

    public function test_a_rule_and_a_module_that_disagree_never_leave_an_empty_calendar(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view', 'shipment.view']);
        $rule = $this->rule($user);

        $deepLink = Livewire::withQueryParams(['cal_rule' => $rule->id, 'cal_module' => Shipment::class])->test(CalendarGridWidget::class);
        $this->assertSame($rule->id, $deepLink->get('ruleId'));
        $this->assertNull($deepLink->get('module'));

        $deepLink->set('module', Shipment::class)
            ->assertSet('ruleId', null)
            ->assertSet('module', Shipment::class);

        $matching = Livewire::withQueryParams([])->test(CalendarGridWidget::class)->call('toggleRule', $rule->id)->set('module', PurchaseRequest::class);
        $matching->assertSet('ruleId', $rule->id)->assertSet('module', PurchaseRequest::class);
    }

    public function test_an_inactive_rule_is_gone_from_the_legend_options_filter_details_and_cells_at_once(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $active = $this->rule($user, ['name' => 'live rule']);
        $paused = $this->rule($user, ['name' => 'paused rule', 'is_active' => false]);
        $this->hit($active, '2026-10-15');
        $this->hit($paused, '2026-10-15');

        $component = Livewire::withQueryParams(['cal_rule' => $paused->id])->test(CalendarGridWidget::class);

        $this->assertNull($component->get('ruleId'));
        $this->assertSame([$active->id => 'live rule'], $component->instance()->ruleOptions);
        $this->assertSame([$active->id], $component->instance()->legend->pluck('id')->all());
        $this->assertNull($component->instance()->ruleDetails);
        $this->assertSame(1, $this->cell($component->instance()->cells, '2026-10-15')['count']);
        $this->assertSame(1, CalendarBoard::monthQuery($user, CalendarRange::forMonth('2026-10-08', false), null, null)->count());
    }

    public function test_the_rule_dropdown_is_capped_but_always_keeps_the_selected_rule(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        CalendarRule::factory()->count(CalendarBoard::RULE_OPTIONS_LIMIT + 5)->create(['user_id' => $user->id]);
        $last = $this->rule($user, ['name' => 'zzz last rule']);

        $this->assertCount(CalendarBoard::RULE_OPTIONS_LIMIT, CalendarBoard::ruleOptions($user));
        $this->assertArrayNotHasKey($last->id, CalendarBoard::ruleOptions($user));
        $this->assertArrayHasKey($last->id, CalendarBoard::ruleOptions($user, $last->id));
        $this->assertCount(CalendarBoard::RULE_OPTIONS_LIMIT + 1, CalendarBoard::ruleOptions($user, $last->id));
    }

    public function test_the_guide_caps_long_condition_lists_and_the_legend_pages_the_rest(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $leaf = fn (int $n): array => ['type' => 'pr_number', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'x'.$n]]];
        $wordy = $this->rule($user, ['name' => 'wordy rule', 'filters' => ['rules' => array_map($leaf, range(1, CalendarBoard::CONDITIONS_LIMIT + 3))]]);

        $details = CalendarBoard::ruleDetails($user, $wordy->id);

        $this->assertCount(CalendarBoard::CONDITIONS_LIMIT, $details['conditions']);
        $this->assertSame(3, $details['conditions_more']);

        Livewire::test(CalendarGridWidget::class)->call('toggleRule', $wordy->id)->assertSeeHtml('<span>+3</span>');

        foreach (range(10, 29) as $n) {
            $this->rule($user, ['name' => 'rule '.$n]);
        }
        $last = $this->rule($user, ['name' => 'zz last rule']);
        $component = Livewire::test(CalendarGridWidget::class)->call('toggleRule', $last->id);

        $this->assertTrue($component->instance()->legend->hasMorePages());
        $component->assertSeeHtml('fi-pagination');
    }

    public function test_month_navigation_moves_the_anchor(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $component = Livewire::test(CalendarGridWidget::class);

        $this->assertSame('2026-10-08', $component->get('anchor'));

        $component->call('nextMonth');
        $this->assertSame('2026-11-08', $component->get('anchor'));

        $component->call('prevMonth')->call('prevMonth');
        $this->assertSame('2026-09-08', $component->get('anchor'));

        $component->call('goToToday');
        $this->assertSame('2026-10-08', $component->get('anchor'));
    }

    public function test_the_month_and_year_jump_lands_on_the_chosen_month_and_clamps_forged_input(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $component = Livewire::test(CalendarGridWidget::class)->call('jump', 2027, 3);
        $this->assertSame('2027-03-01', $component->get('anchor'));

        $component->call('jump', 1, 13)->assertSuccessful();
        $this->assertMatchesRegularExpression('/^\d{4}-12-01$/', $component->get('anchor'));

        $anchor = $component->get('anchor');
        $component->call('jump', 'abc', ['x'])->assertSuccessful();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-01$/', $component->get('anchor'));
        $this->assertNotSame('', $anchor);
    }

    public function test_calendar_signs_carry_translated_tooltips_in_every_locale(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['type' => 'action']);
        $this->hit($rule, '2026-10-08');
        $this->hit($rule, '2026-10-07');

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $html = Livewire::test(CalendarGridWidget::class)->html();

            foreach (['dots', 'overdue'] as $key) {
                $this->assertStringContainsString('title="'.e(__('resources/dashboard/strings.widgets.calendar.hints.'.$key)).'"', $html, $locale.' '.$key);
            }
            $this->assertStringContainsString(' '.e(__('resources/dashboard/strings.widgets.calendar.hints.count')).'"', $html, $locale.' count');
        }
    }

    public function test_the_activity_button_sits_beside_today_and_the_two_filters_share_the_end_group(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $html = Livewire::test(CalendarGridWidget::class)->html();
        $today = strpos($html, 'wire:click="goToToday"');
        $activity = strpos($html, 'cal-nav cal-nav-today', $today + 10);
        $end = strpos($html, 'cal-toolbar-end');
        $rule = strpos($html, 'wire:model.live="ruleId"');
        $module = strpos($html, 'wire:model.live="module"');

        $this->assertTrue($today < $activity && $activity < $end && $end < $rule && $rule < $module);
    }

    public function test_only_today_carries_the_today_tag(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $html = Livewire::test(CalendarGridWidget::class)->html();

        $this->assertSame(1, substr_count($html, 'cal-today-tag'));
    }

    public function test_the_previous_and_next_arrows_flank_the_month_label_not_the_today_button(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $html = Livewire::test(CalendarGridWidget::class)->html();
        $prev = strpos($html, 'wire:click="prevMonth"');
        $label = strpos($html, 'cal-range-btn');
        $next = strpos($html, 'wire:click="nextMonth"');
        $today = strpos($html, 'wire:click="goToToday"');

        $this->assertTrue($prev < $label && $label < $next && $next < $today);
    }

    public function test_the_jalali_calendar_renders_jalali_day_numbers(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        session(['calendar_type' => 'jalali']);

        $component = Livewire::test(CalendarGridWidget::class);

        $this->assertTrue($component->instance()->jalali);
        $this->assertSame(16, $this->cell($component->instance()->cells, '2026-10-08')['day']);
        $this->assertSame('مهر 1405', $component->instance()->rangeLabel);
    }

    public function test_a_month_starting_on_the_week_start_renders_no_phantom_blank_cells(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        Carbon::setTestNow(Carbon::parse('2027-02-10'));

        $component = Livewire::test(CalendarGridWidget::class);

        $this->assertSame(0, $component->instance()->offset);
        $this->assertSame(0, $component->instance()->trailing);
        $this->assertSame(0, substr_count($component->html(), 'cal-day--blank'));
    }

    public function test_forged_url_parameters_of_the_wrong_type_never_break_the_widget(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $forged = Livewire::withQueryParams([
            'cal_date' => ['x'],
            'cal_rule' => ['1'],
            'cal_module' => ['a'],
        ])->test(CalendarGridWidget::class);

        $this->assertSame('2026-10-08', $forged->get('selected'));
        $this->assertNull($forged->get('ruleId'));
        $this->assertNull($forged->get('module'));

        foreach (['abc', '99999999999999999999', '2026-02-30'] as $value) {
            $component = Livewire::withQueryParams(['cal_rule' => $value, 'cal_date' => $value])->test(CalendarGridWidget::class);
            $this->assertNull($component->get('ruleId'));
            $this->assertSame('2026-10-08', $component->get('selected'));
        }
    }

    public function test_tampered_livewire_properties_are_revalidated_on_update(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $foreign = $this->rule(User::factory()->create(), ['visibility' => 'me']);
        $shipmentRule = $this->rule($user, ['subject' => Shipment::class, 'visibility' => 'everyone']);

        Livewire::test(CalendarGridWidget::class)
            ->set('ruleId', $foreign->id)
            ->assertSet('ruleId', null)
            ->set('ruleId', $shipmentRule->id)
            ->assertSet('ruleId', null)
            ->set('module', Shipment::class)
            ->assertSet('module', null)
            ->set('anchor', 'garbage')
            ->assertSet('anchor', '2026-10-08')
            ->set('selected', 'garbage')
            ->assertSet('selected', '2026-10-08')
            ->assertSuccessful();
    }

    public function test_rules_of_modules_the_user_cannot_view_leave_no_trace_in_legend_or_filter_options(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $this->rule($user, ['name' => 'visible module rule']);
        $this->rule(User::factory()->create(), ['name' => 'shipment secret rule', 'subject' => Shipment::class, 'visibility' => 'everyone']);

        $component = Livewire::test(CalendarGridWidget::class);

        $this->assertContains('visible module rule', $component->instance()->ruleOptions);
        $this->assertNotContains('shipment secret rule', $component->instance()->ruleOptions);
        $this->assertSame(['visible module rule'], $component->instance()->legend->pluck('name')->all());
        $this->assertArrayNotHasKey(Shipment::class, $component->instance()->moduleOptions);
        $component->assertDontSee('shipment secret rule');
    }

    public function test_the_month_query_count_does_not_grow_with_the_number_of_hits(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $range = CalendarRange::forMonth('2026-10-08', false);
        $rule = $this->rule($user);
        $this->hit($rule, '2026-10-15');
        CalendarBoard::monthGroups($user, $range, null, null);

        $count = function () use ($user, $range): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            CalendarBoard::monthGroups($user, $range, null, null);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $few = $count();
        foreach (range(1, 25) as $day) {
            $this->hit($rule, sprintf('2026-10-%02d', $day));
            $this->hit($rule, sprintf('2026-10-%02d', $day));
        }

        $this->assertSame(1, $few);
        $this->assertSame($few, $count());
    }

    public function test_a_jalali_leap_esfand_month_has_thirty_days_and_excludes_the_next_farvardin(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        session(['calendar_type' => 'jalali']);
        $rule = $this->rule($user);
        $this->hit($rule, '2025-03-20');
        $this->hit($rule, '2025-03-21');
        $this->hit($rule, '2025-02-18');
        Carbon::setTestNow(Carbon::parse('2025-03-01'));

        $component = Livewire::withQueryParams(['cal_date' => '2025-03-20'])->test(CalendarGridWidget::class);
        $cells = $component->instance()->cells;

        $this->assertCount(30, $cells);
        $this->assertSame('2025-02-19', $cells[0]['iso']);
        $this->assertSame('2025-03-20', end($cells)['iso']);
        $this->assertSame(1, $this->cell($cells, '2025-03-20')['count']);
        $this->assertSame(30, $this->cell($cells, '2025-03-20')['day']);
        $this->assertSame(1, $cells[0]['day']);

        $component->call('nextMonth');
        $this->assertSame('2025-04-19', $component->get('anchor'));
        $this->assertSame(1, $this->cell($component->instance()->cells, '2025-03-21')['count']);
        $this->assertCount(31, $component->instance()->cells);
    }

    public function test_the_shared_metric_legend_also_renders_as_a_plain_view_without_a_slot(): void
    {
        $html = view('components.metric-legend', ['what' => 'what text', 'data' => 'data text', 'why' => 'why text', 'technical' => null])->render();

        $this->assertStringContainsString('what text', $html);
        $this->assertStringContainsString('why text', $html);
    }

    public function test_the_blade_renders_the_grid_and_legend(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $mine = $this->rule($user, ['name' => 'legend rule']);
        $this->hit($mine, '2026-10-15');

        Livewire::test(CalendarGridWidget::class)
            ->assertSeeHtml('cal-grid')
            ->assertDontSeeHtml('cal-agenda')
            ->assertDontSeeHtml('cal-toggle')
            ->assertSeeHtml('--col-span-default: 1 / -1')->assertSeeHtml('--col-span-lg: span 7 / span 7')
            ->assertSee('legend rule')
            ->assertSee(__('resources/dashboard/strings.widgets.calendar.today'))
            ->assertSuccessful();
    }

    public function test_the_dashboard_calendar_tab_grid_carries_the_equal_height_layout_class(): void
    {
        // the dashboard renders the user avatar, whose file is derived from the role
        // name's base segment — it must resolve to an existing avatar image
        $user = User::factory()->create();
        $role = Role::create(['name' => 'agent_'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'purchase_request.view', 'guard_name' => 'web']));
        $user->assignRole($role);
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSeeHtml('cal-tab')
            ->assertSuccessful();
    }

    public function test_the_calendar_tab_stacks_full_width_on_mobile_and_splits_seventy_thirty_from_lg(): void
    {
        $span = fn (string $class): array => (new \ReflectionProperty($class, 'columnSpan'))->getDefaultValue();

        $this->assertSame(['default' => 'full', 'lg' => 7], $span(CalendarGridWidget::class));
        $this->assertSame(['default' => 'full', 'lg' => 3], $span(\App\Filament\Widgets\CalendarDayWidget::class));
        $this->assertSame(['default' => 1, 'lg' => 10], (new \ReflectionClassConstant(\App\Filament\Pages\Dashboard::class, 'TABS'))->getValue()['calendar']['columns']);
    }

    private function selectionParams($component): array
    {
        $events = collect($component->effects['dispatches'] ?? [])->where('name', 'calendar-day-selected')->values();
        $this->assertNotEmpty($events, 'calendar-day-selected was not dispatched');

        return $events->last()['params'];
    }

    public function test_every_grid_navigation_dispatches_a_selection_the_day_widget_follows(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['name' => 'wiring rule']);
        $this->hit($rule, '2026-10-15', ['label' => 'wired october hit']);
        $this->hit($rule, '2026-10-20', ['label' => 'wired late october hit']);
        $this->hit($rule, '2026-11-12', ['label' => 'wired november hit']);

        $grid = Livewire::test(CalendarGridWidget::class);
        $day = Livewire::test(CalendarDayWidget::class);

        $grid->call('selectDate', '2026-10-15');
        $params = $this->selectionParams($grid);
        $this->assertEqualsCanonicalizing(['date', 'ruleId', 'module', 'anchor'], array_keys($params));
        $this->assertSame('2026-10-15', $params['date']);
        $this->assertSame('2026-10-15', $params['anchor']);

        $day->dispatch('calendar-day-selected', ...$params);
        $this->assertSame('2026-10-15', $day->get('date'));
        $day->assertSee('wired october hit')->assertDontSee('wired late october hit');

        $day->filterTable('scope', 'month');
        $day->assertSee('wired october hit')->assertSee('wired late october hit')->assertDontSee('wired november hit');

        $grid->call('nextMonth');
        $params = $this->selectionParams($grid);
        $this->assertSame('2026-11-15', $params['anchor']);
        $this->assertSame('2026-10-15', $params['date']);
        $day->dispatch('calendar-day-selected', ...$params);
        $this->assertSame('2026-11-15', $day->get('anchor'));
        $day->assertSee('wired november hit')->assertDontSee('wired late october hit');

        $grid->call('prevMonth')->call('prevMonth');
        $this->assertSame('2026-09-15', $this->selectionParams($grid)['anchor']);

        $grid->call('jump', 2026, 10);
        $params = $this->selectionParams($grid);
        $this->assertSame('2026-10-01', $params['anchor']);
        $day->dispatch('calendar-day-selected', ...$params);
        $day->assertSee('wired late october hit');
        $this->assertSame('month', $day->get('tableFilters.scope.value'));

        $grid->call('toggleRule', $rule->id);
        $params = $this->selectionParams($grid);
        $this->assertSame($rule->id, $params['ruleId']);
        $day->dispatch('calendar-day-selected', ...$params);
        $this->assertSame($rule->id, $day->get('ruleId'));
    }

    public function test_the_rule_guide_is_cached_per_rule_edit_locale_and_calendar_type(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['name' => 'guide rule']);
        $key = fn (CalendarRule $r, string $locale, string $type): string => 'calendar_rule_guide:'.$r->id.':'.$r->updated_at->timestamp.':'.$locale.':'.$type;
        $sentinel = ['date' => 'SENTINEL DATE', 'conditions' => ['SENTINEL CONDITION']];

        $fresh = CalendarBoard::ruleDetails($user, $rule->id);
        $this->guideKeys[] = $key($rule, 'en', 'g');
        $this->assertTrue(Cache::has($key($rule, 'en', 'g')));
        $this->assertNotSame('SENTINEL DATE', $fresh['date']);

        Cache::put($key($rule, 'en', 'g'), $sentinel, 60);
        $this->assertSame('SENTINEL DATE', CalendarBoard::ruleDetails($user, $rule->id)['date']);
        $this->assertSame(['SENTINEL CONDITION'], CalendarBoard::ruleDetails($user, $rule->id)['conditions']);

        app()->setLocale('fa');
        $this->assertNotSame('SENTINEL DATE', CalendarBoard::ruleDetails($user, $rule->id)['date']);
        app()->setLocale('en');

        session(['calendar_type' => 'jalali']);
        $this->assertNotSame('SENTINEL DATE', CalendarBoard::ruleDetails($user, $rule->id)['date']);
        session(['calendar_type' => 'gregorian']);

        Carbon::setTestNow(Carbon::parse('2026-10-08')->addMinutes(5));
        $rule->update(['name' => 'guide rule edited']);
        $rule->refresh();
        $this->assertNotContains($key($rule, 'en', 'g'), $this->guideKeys);
        $this->guideKeys[] = $key($rule, 'en', 'g');
        $this->assertNotSame('SENTINEL DATE', CalendarBoard::ruleDetails($user, $rule->id)['date']);
        $this->assertTrue(Cache::has($key($rule, 'en', 'g')));
    }

    public function test_the_month_agenda_toggle_and_its_translations_are_gone_for_good(): void
    {
        $symbols = ['cal-agenda', 'cal-toggle', 'agendaPage', 'agendaRows', 'agendaHits', 'agendaDays', 'agenda_range', 'cal-pager'];
        $found = [];

        foreach ([app_path('Filament'), app_path('Services/Calendar'), app_path('Livewire'), resource_path('views/filament'), resource_path('css'), resource_path('js'), lang_path('en/resources'), lang_path('fa/resources'), lang_path('fr/resources')] as $dir) {
            foreach (Finder::create()->in($dir)->files()->name(['*.php', '*.css', '*.js']) as $file) {
                $source = file_get_contents($file->getPathname());

                foreach ($symbols as $symbol) {
                    if (str_contains($source, $symbol)) {
                        $found[] = $symbol.' in '.$file->getPathname();
                    }
                }
            }
        }

        $this->assertSame([], $found);

        foreach (['en', 'fa', 'fr'] as $locale) {
            $calendar = Lang::get('resources/dashboard/strings.widgets.calendar', [], $locale);

            foreach (['agenda', 'month', 'empty', 'agenda_range', 'page_range', 'page_previous', 'page_next'] as $removed) {
                $this->assertArrayNotHasKey($removed, $calendar, $locale);
            }
        }

        $this->assertFileDoesNotExist(resource_path('views/filament/widgets/calendar-grid/agenda.blade.php'));
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $this->assertStringNotContainsString('cal-agenda', Livewire::test(CalendarGridWidget::class)->html());
    }

    public function test_read_items_leave_the_grid_and_return_after_unmark_or_a_date_change(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['name' => 'quiet rule']);
        $read = $this->hit($rule, '2026-10-15');
        $this->hit($rule, '2026-10-15');
        $this->hit($rule, '2026-10-16');
        $seen = app(CalendarSeen::class);
        $seen->mark($user, [$read]);

        $grid = Livewire::test(CalendarGridWidget::class);
        $this->assertSame(1, $this->cell($grid->instance()->cells, '2026-10-15')['count']);

        $seen->mark($user, CalendarHit::query()->whereDate('event_date', '2026-10-16')->get());
        $grid->dispatch('calendar-read-changed')->assertSuccessful();
        $this->assertSame(0, $this->cell($grid->instance()->cells, '2026-10-16')['count']);

        $seen->unmark($user, [$read]);
        $this->assertSame(2, $this->cell(Livewire::test(CalendarGridWidget::class)->instance()->cells, '2026-10-15')['count']);

        $seen->mark($user, [$read]);
        $read->update(['event_date' => '2026-10-20']);
        CalendarSeen::flush();
        $cells = Livewire::test(CalendarGridWidget::class)->instance()->cells;
        $this->assertSame(1, $this->cell($cells, '2026-10-20')['count']);
    }
}
