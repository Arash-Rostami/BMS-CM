<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CalendarGridWidget;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\Display\CalendarBoard;
use App\Services\Calendar\Display\CalendarRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarGridWidgetTest extends TestCase
{
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
        Queue::fake(); // observers are live: never let a dispatch run inline under QUEUE_CONNECTION=sync
    }

    protected function tearDown(): void
    {
        CalendarHit::flushVisibleRuleIds();
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

    public function test_agenda_items_link_to_the_record_and_orphans_render_without_a_link(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user);
        $record = PurchaseRequest::factory()->create();
        CalendarHit::factory()->create([
            'calendar_rule_id' => $rule->id,
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $record->id,
            'label' => 'linked item',
            'event_date' => '2026-10-15',
        ]);
        $this->hit($rule, '2026-10-16', ['label' => 'orphan item', 'subject_id' => 2000000000]);

        $component = Livewire::test(CalendarGridWidget::class);
        $hits = collect($component->instance()->agenda)->flatMap(fn (array $day): array => $day['hits']);

        $this->assertSame(CalendarModules::url($record), $hits->firstWhere('label', 'linked item')['url']);
        $this->assertNull($hits->firstWhere('label', 'orphan item')['url']);
        $component->assertSeeHtml('href="'.CalendarModules::url($record).'"');
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

    public function test_a_forged_jump_target_is_clamped_instead_of_failing(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $component = Livewire::test(CalendarGridWidget::class)
            ->set('jumpYear', 1)
            ->set('jumpMonth', 13)
            ->call('jump')
            ->assertSuccessful();

        $this->assertMatchesRegularExpression('/^\d{4}-12-01$/', $component->get('anchor'));
    }

    public function test_non_numeric_jump_updates_are_sanitized_not_fatal(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        Livewire::test(CalendarGridWidget::class)
            ->set('jumpYear', 'abc')
            ->assertSet('jumpYear', null)
            ->set('jumpMonth', 'abc')
            ->assertSet('jumpMonth', null)
            ->set('jumpMonth', 99)
            ->assertSet('jumpMonth', 12)
            ->set('jumpYear', '2027')
            ->assertSet('jumpYear', 2027)
            ->call('jump')
            ->assertSuccessful();
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

    public function test_the_blade_renders_the_grid_agenda_and_legend(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $mine = $this->rule($user, ['name' => 'legend rule']);
        $this->hit($mine, '2026-10-15', ['label' => 'agenda item']);

        Livewire::test(CalendarGridWidget::class)
            ->assertSeeHtml('cal-grid')
            ->assertSeeHtml('cal-agenda')
            ->assertSeeHtml('--col-span-default: 1 / -1')->assertSeeHtml('--col-span-lg: span 7 / span 7')
            ->assertSee('agenda item')
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
}
