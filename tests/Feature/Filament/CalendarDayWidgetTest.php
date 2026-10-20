<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\CalendarDayWidget;
use App\Jobs\ExportCalendarHits;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\CalendarSeen;
use App\Services\Calendar\Display\CalendarBoard;
use App\Services\Calendar\Display\CalendarRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarDayWidgetTest extends TestCase
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
        CalendarSeen::flush();
        Queue::fake(); // observers are live: never let a dispatch run inline under QUEUE_CONNECTION=sync
    }

    protected function tearDown(): void
    {
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

        $role = Role::create(['name' => 'calendar_day_test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private int $nextSubjectId = 1;

    private function hit(CalendarRule $rule, string $eventDate, array $overrides = []): CalendarHit
    {
        return CalendarHit::factory()->create(array_merge([
            'calendar_rule_id' => $rule->id,
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $this->nextSubjectId++,
            'label' => 'probe hit',
            'event_date' => $eventDate,
        ], $overrides));
    }

    public function test_the_day_table_lists_hits_grouped_by_module_with_record_links(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view', 'shipment.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'day rule']);

        $request = PurchaseRequest::factory()->create();
        $this->nextSubjectId = $request->id + 1;
        $this->hit($rule, '2026-10-15', ['subject_id' => $request->id, 'label' => 'request hit']);
        $this->hit($rule, '2026-10-15', ['subject_type' => Shipment::class, 'subject_id' => 1, 'label' => 'shipment hit']);
        $this->hit($rule, '2026-10-16', ['label' => 'other day hit']);

        Livewire::test(CalendarDayWidget::class)
            ->call('syncSelection', date: '2026-10-15')
            ->assertSeeHtml('--col-span-default: 1 / -1')->assertSeeHtml('--col-span-lg: span 3 / span 3')
            ->assertSee('request hit')
            ->assertSee('shipment hit')
            ->assertDontSee('other day hit')
            ->assertSee(CalendarModules::label(PurchaseRequest::class))
            ->assertSee(CalendarModules::label(Shipment::class))
            ->assertSee(__('resources/dashboard/strings.widgets.calendar.day.module'))
            ->assertDontSee('Subject type')
            ->assertSeeHtml(CalendarModules::url($request))
            ->assertSuccessful();
    }

    public function test_the_day_cards_are_paginated_instead_of_listing_every_hit(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'page rule']);

        foreach (range(1, 7) as $n) {
            $this->hit($rule, '2026-10-15', ['label' => 'paged hit '.$n]);
        }

        $component = Livewire::test(CalendarDayWidget::class)->call('syncSelection', date: '2026-10-15');

        $this->assertSame(5, substr_count($component->html(), 'paged hit '));
        $component->assertSeeHtml('fi-pagination');
    }

    public function test_an_empty_day_names_the_date_and_points_to_the_next_event(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'next rule']);
        $this->hit($rule, '2026-10-20', ['label' => 'later hit']);

        Livewire::test(CalendarDayWidget::class)
            ->call('syncSelection', date: '2026-10-15')
            ->assertSee(__('resources/dashboard/strings.widgets.calendar.day.empty_heading', ['date' => adaptiveDate('2026-10-15')]))
            ->assertSee(__('resources/dashboard/strings.widgets.calendar.day.next', ['date' => adaptiveDate('2026-10-20')]))
            ->assertDontSee('later hit');
    }

    public function test_the_whole_month_option_lists_every_event_of_the_month_paginated_and_toggles_back(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'month rule']);
        foreach (range(10, 16) as $day) {
            $this->hit($rule, '2026-10-'.$day, ['label' => 'monthly hit '.$day]);
        }
        $this->hit($rule, '2026-11-10', ['label' => 'next month hit']);

        $component = Livewire::test(CalendarDayWidget::class)
            ->call('syncSelection', date: '2026-10-12', anchor: '2026-10-01');

        $component->assertSee('monthly hit 12')->assertDontSee('monthly hit 13');

        $component->filterTable('scope', 'month');
        $this->assertSame('month', $component->get('tableFilters.scope.value'));
        $this->assertSame(5, substr_count($component->html(), 'monthly hit '));
        $component->assertSeeHtml('fi-pagination')->assertDontSee('next month hit');

        $component->call('syncSelection', date: '2026-10-12', anchor: '2026-11-01')->assertSee('next month hit')->assertDontSee('monthly hit 10');

        $component->filterTable('scope', 'day');
        $this->assertSame('day', $component->get('tableFilters.scope.value'));
        $component->assertSee('monthly hit 12')->assertDontSee('next month hit')->assertDontSee('monthly hit 13');
    }

    public function test_forged_scope_filter_and_anchor_values_are_sanitised(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        Livewire::test(CalendarDayWidget::class)
            ->set('tableFilters.scope.value', ['x'])
            ->set('tableFilters.read.value', ['z'])
            ->set('anchor', ['y'])
            ->assertSuccessful();
    }

    public function test_modules_are_ordered_by_their_localized_label_not_by_class_name(): void
    {
        app()->setLocale('fa');
        $user = $this->actingAsUserWithPermissions(['purchase_request.view', 'shipment.view', 'payment.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'order rule']);
        $classes = [PurchaseRequest::class, Shipment::class, Payment::class];

        foreach ($classes as $index => $class) {
            $this->hit($rule, '2026-10-15', ['subject_type' => $class, 'subject_id' => $index + 1, 'label' => 'order hit '.$index]);
        }

        $labels = collect($classes)->map(fn (string $class): string => CalendarModules::label($class))->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        $html = Livewire::test(CalendarDayWidget::class)->call('syncSelection', date: '2026-10-15')->html();
        $positions = array_map(fn (string $label): int|false => mb_strpos($html, $label), $labels);

        $this->assertNotContains(false, $positions, json_encode($labels, JSON_UNESCAPED_UNICODE));
        $this->assertSame($positions, collect($positions)->sort()->values()->all(), json_encode([$labels, $positions], JSON_UNESCAPED_UNICODE));
    }

    public function test_the_day_widget_survives_a_calendar_toggle_event(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        Livewire::test(CalendarDayWidget::class)->dispatch('calendar-toggled')->assertSuccessful();
    }

    public function test_the_export_bulk_action_dispatches_the_export_job_for_the_selected_hits(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        $selected = $this->hit($rule, '2026-10-15', ['label' => 'exported hit']);
        $unselected = $this->hit($rule, '2026-10-16', ['label' => 'left behind hit']);

        Livewire::test(CalendarDayWidget::class)
            ->call('syncSelection', date: '2026-10-15')
            ->callTableBulkAction('exportHits', [$selected]);

        Queue::assertPushed(ExportCalendarHits::class, fn (ExportCalendarHits $job): bool => $job->ids === [$selected->id] && $job->userId === $user->id);
        Queue::assertNotPushed(ExportCalendarHits::class, fn (ExportCalendarHits $job): bool => in_array($unselected->id, $job->ids));
    }

    public function test_a_hit_whose_record_no_longer_exists_renders_without_a_link_instead_of_failing(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        $this->hit($rule, '2026-10-15', ['subject_id' => 2147483000, 'label' => 'orphan hit']);

        Livewire::test(CalendarDayWidget::class)
            ->call('syncSelection', date: '2026-10-15')
            ->assertSee('orphan hit')
            ->assertSuccessful();
    }

    public function test_forged_url_parameters_and_tampered_filters_never_reveal_hidden_hits(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $private = CalendarRule::factory()->create(['user_id' => $owner->id, 'visibility' => 'me']);
        $this->actingAs($user);
        $this->hit($private, '2026-10-15', ['label' => 'private rule hit']);
        $mine = CalendarRule::factory()->create(['user_id' => $user->id]);
        $this->hit($mine, '2026-10-15', ['label' => 'shipment hit', 'subject_type' => Shipment::class]);

        $forged = Livewire::withQueryParams(['cal_date' => ['x'], 'cal_rule' => ['1'], 'cal_module' => ['a']])->test(CalendarDayWidget::class);
        $this->assertNull($forged->get('ruleId'));
        $this->assertNull($forged->get('module'));
        $forged->assertSuccessful();

        Livewire::test(CalendarDayWidget::class)
            ->call('syncSelection', date: '2026-10-15', ruleId: $private->id)
            ->assertDontSee('private rule hit')
            ->call('syncSelection', date: '2026-10-15', module: Shipment::class)
            ->assertDontSee('shipment hit');
    }

    public function test_tampered_widget_properties_are_sanitised_instead_of_crashing_the_request(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $private = CalendarRule::factory()->create(['user_id' => $owner->id, 'visibility' => 'me']);
        $this->actingAs($user);

        Livewire::test(CalendarDayWidget::class)
            ->set('ruleId', [1])->assertSet('ruleId', null)
            ->set('module', ['a'])->assertSet('module', null)
            ->set('date', ['x'])->assertSet('date', '2026-10-08')
            ->set('ruleId', $private->id)->assertSet('ruleId', null)
            ->set('module', Shipment::class)->assertSet('module', null)
            ->assertSuccessful();
    }

    public function test_changing_the_day_returns_to_page_one_and_drops_rows_ticked_on_the_previous_day(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        foreach (range(1, 7) as $n) {
            $this->hit($rule, '2026-10-15', ['label' => 'first day '.$n]);
        }
        foreach (range(1, 3) as $n) {
            $this->hit($rule, '2026-10-16', ['label' => 'second day '.$n]);
        }

        $component = Livewire::test(CalendarDayWidget::class)
            ->call('syncSelection', date: '2026-10-15')
            ->call('setPage', 2);
        $this->assertSame(2, $component->instance()->getTablePage());

        $component->call('syncSelection', date: '2026-10-16')->assertDispatched('deselectAllTableRecords');

        $this->assertSame(1, $component->instance()->getTablePage());
        $this->assertSame(3, substr_count($component->html(), 'second day '));
    }

    public function test_hits_of_an_inactive_rule_never_reach_the_day_table(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $paused = CalendarRule::factory()->create(['user_id' => $user->id, 'is_active' => false]);
        $live = CalendarRule::factory()->create(['user_id' => $user->id]);
        $this->hit($paused, '2026-10-15', ['label' => 'paused hit']);
        $this->hit($live, '2026-10-15', ['label' => 'live hit']);

        Livewire::test(CalendarDayWidget::class)
            ->call('syncSelection', date: '2026-10-15')
            ->assertSee('live hit')
            ->assertDontSee('paused hit');
    }

    public function test_a_forged_selection_event_of_the_wrong_type_falls_back_to_safe_defaults(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $component = Livewire::test(CalendarDayWidget::class)
            ->call('syncSelection', date: ['x'], ruleId: 'abc', module: ['a'])
            ->assertSuccessful();

        $this->assertSame('2026-10-08', $component->get('date'));
        $this->assertNull($component->get('ruleId'));
        $this->assertNull($component->get('module'));
    }

    public function test_overdue_action_hits_carry_the_overdue_badge_and_past_heads_up_hits_are_absent(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $headsUp = CalendarRule::factory()->create(['user_id' => $user->id]);
        $action = CalendarRule::factory()->create(['user_id' => $user->id, 'type' => 'action']);
        $this->hit($headsUp, '2026-10-05', ['label' => 'gone heads up']);
        $this->hit($action, '2026-10-05', ['label' => 'overdue action']);

        Livewire::test(CalendarDayWidget::class)
            ->call('syncSelection', date: '2026-10-05')
            ->assertSee('overdue action')
            ->assertSee(__('resources/dashboard/strings.widgets.calendar.overdue'))
            ->assertDontSee('gone heads up');
    }

    public function test_the_whole_month_scope_applies_the_same_visibility_rules_as_a_single_day(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $private = CalendarRule::factory()->create(['user_id' => $owner->id, 'visibility' => 'me']);
        $this->actingAs($user);
        $headsUp = CalendarRule::factory()->create(['user_id' => $user->id, 'type' => 'heads_up']);
        $action = CalendarRule::factory()->create(['user_id' => $user->id, 'type' => 'action']);
        $paused = CalendarRule::factory()->create(['user_id' => $user->id, 'is_active' => false]);

        $this->hit($action, '2026-10-12', ['label' => 'month shown hit']);
        $this->hit($action, '2026-10-03', ['label' => 'month overdue action']);
        $this->hit($headsUp, '2026-10-03', ['label' => 'month past heads up']);
        $this->hit($private, '2026-10-12', ['label' => 'month private hit']);
        $this->hit($paused, '2026-10-12', ['label' => 'month paused hit']);
        $this->hit($action, '2026-10-12', ['label' => 'month forbidden module hit', 'subject_type' => Shipment::class]);
        $deleted = PurchaseRequest::factory()->create();
        $this->hit($action, '2026-10-13', ['label' => 'month deleted subject hit', 'subject_id' => $deleted->id]);
        $deleted->delete();

        $component = Livewire::test(CalendarDayWidget::class)
            ->call('syncSelection', date: '2026-10-12', anchor: '2026-10-01')
            ->filterTable('scope', 'month');

        $component->assertSee('month shown hit')
            ->assertSee('month overdue action')
            ->assertSee('month deleted subject hit')
            ->assertDontSee('month past heads up')
            ->assertDontSee('month private hit')
            ->assertDontSee('month paused hit')
            ->assertDontSee('month forbidden module hit')
            ->assertSuccessful();

        $range = CalendarRange::forMonth('2026-10-01', false);
        $day = CalendarBoard::dayQuery($user, Carbon::parse('2026-10-12'), null, null)->pluck('id')->all();
        $month = CalendarBoard::monthQuery($user, $range, null, null)->pluck('id')->all();
        $this->assertSame([], array_diff($day, $month));
        $this->assertCount(3, $month);
    }

    public function test_the_month_scope_shows_each_cards_date(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        $this->hit($rule, '2026-10-12', ['label' => 'dated hit']);
        $this->hit($rule, '2026-10-19', ['label' => 'later dated hit']);
        $dated = adaptiveDate('2026-10-19');

        $component = Livewire::test(CalendarDayWidget::class)->call('syncSelection', date: '2026-10-12', anchor: '2026-10-01');
        $component->assertDontSee($dated);

        $component->filterTable('scope', 'month')->assertSee('later dated hit')->assertSee($dated)->assertSee(adaptiveDate('2026-10-12'));
    }

    public function test_changing_the_selection_in_the_month_scope_returns_to_page_one(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        foreach (range(1, 7) as $n) {
            $this->hit($rule, '2026-10-1'.$n, ['label' => 'scoped hit '.$n]);
        }

        $component = Livewire::test(CalendarDayWidget::class)
            ->call('syncSelection', date: '2026-10-12', anchor: '2026-10-01')
            ->filterTable('scope', 'month')
            ->call('setPage', 2);
        $this->assertSame(2, $component->instance()->getTablePage());

        $component->call('syncSelection', date: '2026-10-14', anchor: '2026-10-01');

        $this->assertSame(1, $component->instance()->getTablePage());
        $this->assertSame('month', $component->get('tableFilters.scope.value'));
    }

    public function test_forged_month_scope_props_never_crash_the_month_scope(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        $this->hit($rule, '2026-10-08', ['label' => 'fallback month hit']);

        $component = Livewire::test(CalendarDayWidget::class)
            ->filterTable('scope', 'month')
            ->set('anchor', ['y'])
            ->assertSuccessful()
            ->assertSee('fallback month hit');
        $this->assertSame('2026-10-08', $component->get('anchor'));

        $component->set('anchor', 'garbage')->set('tableFilters.scope.value', ['x'])->assertSuccessful();
    }

    public function test_day_cards_state_why_the_hit_is_listed_without_extra_queries_per_card(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id, 'filters' => ['rules' => [['type' => 'pr_number', 'data' => ['operator' => 'equals', 'settings' => ['text' => 'ZZ1']]]]]]);
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $private = CalendarRule::factory()->create(['user_id' => $owner->id, 'visibility' => 'me', 'filters' => ['rules' => [['type' => 'pr_number', 'data' => ['operator' => 'equals', 'settings' => ['text' => 'SECRET9']]]]]]);
        $this->actingAs($user);
        $this->hit($rule, '2026-10-15', ['label' => 'card one']);
        $this->hit($private, '2026-10-15', ['label' => 'private card']);

        $count = function (int $cards) use ($rule): int {
            foreach (range(1, $cards) as $n) {
                $this->hit($rule, '2026-10-15', ['label' => 'extra '.$n]);
            }
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test(CalendarDayWidget::class)->call('syncSelection', date: '2026-10-15');
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };
        $few = $count(1);
        $many = $count(3);

        Livewire::test(CalendarDayWidget::class)->call('syncSelection', date: '2026-10-15')
            ->assertSee('ZZ1')
            ->assertSee(adaptiveDate('2026-10-15'))
            ->assertDontSee('SECRET9');
        $this->assertLessThanOrEqual($few + 4, $many);
    }

    private function hint(string $key): string
    {
        return substr(json_encode(__($key)), 1, -1);
    }

    public function test_the_rule_and_overdue_badges_carry_hover_hints(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $headsUp = CalendarRule::factory()->create(['user_id' => $user->id, 'type' => 'heads_up']);
        $action = CalendarRule::factory()->create(['user_id' => $user->id, 'type' => 'action']);
        $this->hit($headsUp, '2026-10-12', ['label' => 'soft hit']);
        $this->hit($action, '2026-10-05', ['label' => 'late hit']);

        $hints = 'resources/dashboard/strings.widgets.calendar.hints.';
        Livewire::test(CalendarDayWidget::class)->call('syncSelection', date: '2026-10-12')
            ->assertSeeHtml($this->hint($hints.'heads_up'))
            ->assertDontSeeHtml($this->hint($hints.'action'));

        Livewire::test(CalendarDayWidget::class)->call('syncSelection', date: '2026-10-05')
            ->assertSeeHtml($this->hint($hints.'action'))
            ->assertSeeHtml('title="'.e(__($hints.'overdue')).'"')
            ->assertDontSeeHtml($this->hint($hints.'heads_up'));
    }

    public function test_the_export_bulk_action_still_works_while_the_whole_month_is_shown(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        $onOtherDay = $this->hit($rule, '2026-10-20', ['label' => 'month export hit']);

        Livewire::test(CalendarDayWidget::class)
            ->call('syncSelection', date: '2026-10-08', anchor: '2026-10-01')
            ->filterTable('scope', 'month')
            ->callTableBulkAction('exportHits', [$onOtherDay]);

        Queue::assertPushed(ExportCalendarHits::class, fn (ExportCalendarHits $job): bool => $job->ids === [$onOtherDay->id] && $job->userId === $user->id);
    }

    public function test_the_native_scope_and_read_filters_default_to_one_day_and_unread_even_after_a_reset(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $component = Livewire::test(CalendarDayWidget::class);
        $this->assertSame('day', $component->get('tableFilters.scope.value'));
        $this->assertSame('unread', $component->get('tableFilters.read.value'));
        $component->assertSee(__('resources/dashboard/strings.widgets.calendar.day.scope_label'))
            ->assertSee(__('resources/dashboard/strings.widgets.calendar.day.read_label'));

        $component->assertSeeHtml('fi-ta-filters')->assertDontSee(__('resources/dashboard/strings.widgets.calendar.day.read_label').': ');
        $component->filterTable('scope', 'month')->filterTable('read', 'all');
        $component->assertSee(__('resources/dashboard/strings.widgets.calendar.day.read_label').': '.__('resources/dashboard/strings.widgets.calendar.day.read_all'))
            ->assertSee(__('resources/dashboard/strings.widgets.calendar.day.scope_label').': '.__('resources/dashboard/strings.widgets.calendar.day.scope_month'));
        $component->call('syncSelection', date: '2026-10-12', anchor: '2026-10-01');
        $this->assertSame('month', $component->get('tableFilters.scope.value'));
        $this->assertSame('all', $component->get('tableFilters.read.value'));

        $component->call('removeTableFilters');
        $this->assertSame('day', $component->get('tableFilters.scope.value'));
        $this->assertSame('unread', $component->get('tableFilters.read.value'));

        $component->filterTable('read', 'all')->call('resetTableFiltersForm');
        $this->assertSame('unread', $component->get('tableFilters.read.value'));
    }

    public function test_the_read_state_filter_splits_unread_and_read_cards_and_read_cards_show_their_timestamp(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        $this->hit($rule, '2026-10-15', ['label' => 'fresh card']);
        $done = $this->hit($rule, '2026-10-15', ['label' => 'done card']);
        app(CalendarSeen::class)->mark($user, [$done]);
        $line = __('resources/dashboard/strings.widgets.calendar.read.seen', ['when' => adaptiveDate(now(), true)]);

        $component = Livewire::test(CalendarDayWidget::class)->call('syncSelection', date: '2026-10-15');
        $component->assertSee('fresh card')->assertDontSee('done card')->assertDontSee($line);

        $component->filterTable('read', 'read')->assertSee('done card')->assertDontSee('fresh card')->assertSee($line)->assertSeeHtml('cal-day-read');
        $component->filterTable('read', 'all')->assertSee('done card')->assertSee('fresh card');
        $this->assertSame(1, substr_count($component->html(), $line));
    }

    public function test_the_row_action_marks_and_unmarks_and_tells_the_grid(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        $hit = $this->hit($rule, '2026-10-15', ['label' => 'toggle card']);

        $component = Livewire::test(CalendarDayWidget::class)->call('syncSelection', date: '2026-10-15')
            ->assertSee(__('resources/dashboard/strings.widgets.calendar.read.mark'))
            ->callTableAction('toggleRead', $hit)
            ->assertDispatched('calendar-read-changed')
            ->assertDontSee('toggle card');
        CalendarSeen::flush();
        $this->assertSame([$hit->id], app(CalendarSeen::class)->readIds($user->fresh()));

        $component->filterTable('read', 'read')->assertSee('toggle card')->assertSee(__('resources/dashboard/strings.widgets.calendar.read.unmark'))
            ->callTableAction('toggleRead', $hit)
            ->assertDontSee('toggle card');
        CalendarSeen::flush();
        $this->assertSame([], app(CalendarSeen::class)->readIds($user->fresh()));
    }

    public function test_the_bulk_actions_mark_selected_cards_read_or_unread(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        $a = $this->hit($rule, '2026-10-15', ['label' => 'bulk a']);
        $b = $this->hit($rule, '2026-10-15', ['label' => 'bulk b']);

        $component = Livewire::test(CalendarDayWidget::class)->call('syncSelection', date: '2026-10-15')
            ->callTableBulkAction('markRead', [$a, $b])
            ->assertDispatched('calendar-read-changed')
            ->assertDontSee('bulk a')->assertDontSee('bulk b');
        CalendarSeen::flush();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], app(CalendarSeen::class)->readIds($user->fresh()));

        $component->filterTable('read', 'read')->callTableBulkAction('markUnread', [$a]);
        CalendarSeen::flush();
        $this->assertSame([$b->id], app(CalendarSeen::class)->readIds($user->fresh()));
    }

    public function test_the_scope_and_read_filters_drive_the_empty_state_hint(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        $later = $this->hit($rule, '2026-10-20', ['label' => 'hinted hit']);
        $next = __('resources/dashboard/strings.widgets.calendar.day.next', ['date' => adaptiveDate('2026-10-20')]);
        $nothing = __('resources/dashboard/strings.widgets.calendar.day.nothing_next');

        $component = Livewire::test(CalendarDayWidget::class)->call('syncSelection', date: '2026-10-15', anchor: '2026-10-01')->assertSee($next);
        app(CalendarSeen::class)->mark($user, [$later]);
        $component->call('$refresh')->assertSee($nothing);
        $component->filterTable('read', 'all')->assertSee($next);
        $component->filterTable('scope', 'month')->assertSee('hinted hit');
    }

    public function test_forged_filter_values_fall_back_to_the_defaults_without_crashing(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        $this->hit($rule, '2026-10-08', ['label' => 'default card']);

        Livewire::test(CalendarDayWidget::class)
            ->set('tableFilters.read.value', 'bogus')
            ->set('tableFilters.scope.value', ['x'])
            ->assertSuccessful()
            ->assertSee('default card');
    }

    public function test_the_day_card_badge_maps_urgency_to_colour_and_icon(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $action = CalendarRule::factory()->create(['user_id' => $user->id, 'type' => 'action']);
        $headsUp = CalendarRule::factory()->create(['user_id' => $user->id, 'type' => 'heads_up']);
        $cases = [
            [$action, '2026-10-06', 'overdue', 'danger', 'heroicon-m-exclamation-triangle'],
            [$action, '2026-10-08', 'today', 'warning', 'heroicon-m-clock'],
            [$headsUp, '2026-10-10', 'soon', 'info', 'heroicon-m-calendar-days'],
            [$headsUp, '2026-10-17', 'later', 'gray', 'heroicon-m-calendar'],
            [$action, '2026-10-17', 'later', 'success', 'heroicon-m-calendar'],
        ];

        $component = Livewire::test(CalendarDayWidget::class)->call('syncSelection', date: '2026-10-08');
        $column = $component->instance()->getTable()->getColumn('rule.name');

        foreach ($cases as [$rule, $date, $urgency, $color, $icon]) {
            $hit = $this->hit($rule, $date, ['label' => 'urgency '.$urgency])->load('rule');
            $this->assertSame($urgency, $hit->urgency());
            $column->record($hit);
            $this->assertSame($color, $column->getColor('x'), $date.' '.$urgency);
            $this->assertSame($icon, $column->getIcon('x'));
        }

        $past = $this->hit($headsUp, '2026-10-05')->load('rule');
        $this->assertSame('today', $past->urgency());
    }

    public function test_the_toolbar_hides_the_group_direction_and_keeps_group_by_and_the_filter_trigger(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        $this->hit($rule, '2026-10-15', ['label' => 'toolbar hit']);

        $component = Livewire::test(CalendarDayWidget::class)->call('syncSelection', date: '2026-10-15');

        $this->assertTrue($component->instance()->getTable()->isGroupingDirectionSettingHidden());
        $component->assertSee(__('filament-tables::table.grouping.fields.group.label'))
            ->assertDontSee(__('filament-tables::table.grouping.fields.direction.label'))
            ->assertSeeHtml('fi-ta-filters')
            ->assertSeeHtml('fi-ta-header-toolbar')
            ->assertSee(CalendarModules::label(PurchaseRequest::class));
    }
}
