<?php

namespace Tests\Feature\Livewire;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\LandingPage;
use App\Livewire\LandingPage\Attention;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\CalendarAlertNotification;
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\CalendarSeen;
use App\Services\Calendar\Display\CalendarBoard;
use App\Services\Calendar\Display\CalendarRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class LandingPageAttentionTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $warmedCacheKeys = [];

    private int $nextSubjectId = 1;

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
        Carbon::setTestNow();

        foreach ($this->warmedCacheKeys as $key) {
            Cache::forget($key);
        }

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

        $role = Role::create(['name' => 'landing_attention_test_role_'.uniqid(), 'guard_name' => 'web']);
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

        if ($acting !== null) {
            $this->actingAs($acting);
        }

        return $rule;
    }

    private function sharedRule(): CalendarRule
    {
        return $this->rule(User::factory()->create(), ['visibility' => 'everyone']);
    }

    private function hit(CalendarRule $rule, string $eventDate, string $label, string $subjectType = PurchaseRequest::class): CalendarHit
    {
        return CalendarHit::factory()->create([
            'calendar_rule_id' => $rule->id,
            'subject_type' => $subjectType,
            'subject_id' => $this->nextSubjectId++,
            'label' => $label,
            'event_date' => $eventDate,
        ]);
    }

    private function attentionComponent(): Testable
    {
        return Livewire::test(Attention::class, ['isRtl' => false]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allRows(Testable $component): array
    {
        $rows = [];

        foreach (range(1, $component->viewData('paginator')->lastPage()) as $page) {
            $component->call('setPage', $page);
            $rows = array_merge($rows, $component->viewData('paginator')->items());
        }

        $component->call('setPage', 1);

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function renderedLabels(Testable $component): array
    {
        return collect($this->allRows($component))->pluck('label')->all();
    }

    public function test_rows_are_ordered_overdue_today_then_the_rest_of_the_month(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['type' => 'action']);
        $this->hit($rule, '2026-09-20', 'oldest overdue');
        $this->hit($rule, '2026-10-05', 'newer overdue');
        $this->hit($rule, '2026-10-08', 'due today');
        $this->hit($rule, '2026-10-11', 'due soon');
        $this->hit($rule, '2026-10-15', 'due last');
        $this->hit($rule, '2026-10-20', 'later this month');
        $this->hit($rule, '2026-11-02', 'next month');

        $component = $this->attentionComponent();
        $rows = $this->allRows($component);

        $this->assertSame(
            ['oldest overdue', 'newer overdue', 'due today', 'due soon', 'due last', 'later this month'],
            $this->renderedLabels($component)
        );
        $this->assertTrue($rows[0]['overdue']);
        $this->assertTrue($rows[1]['overdue']);
        $this->assertFalse($rows[2]['overdue']);
        $this->assertFalse($rows[4]['overdue']);
        $component->assertDontSee('next month');
    }

    public function test_rows_carry_their_tier_and_sort_by_date_then_id(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view', 'shipment.view']);
        $action = $this->rule($user, ['type' => 'action']);
        $headsUp = $this->rule($user, ['type' => 'heads_up']);
        $this->hit($headsUp, '2026-10-12', 'b heads up');
        $this->hit($action, '2026-10-12', 'z action');
        $this->hit($action, '2026-10-12', 'a shipment action', Shipment::class);
        $this->hit($action, '2026-10-12', 'a request action');
        $this->hit($action, '2026-10-08', 'today hit');
        $this->hit($action, '2026-10-03', 'overdue hit');

        $rows = collect($this->allRows($this->attentionComponent()));

        $this->assertSame(['overdue hit', 'today hit', 'b heads up', 'z action', 'a shipment action', 'a request action'], $rows->pluck('label')->all());
        $this->assertSame([0, 1, 2, 2, 2, 2], $rows->pluck('tier')->all());
    }

    public function test_the_loaded_rows_are_paged_eight_at_a_time_with_standard_pagination_and_a_forged_page_is_clamped(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['type' => 'action']);

        foreach (range(0, 11) as $offset) {
            $this->hit($rule, Carbon::parse('2026-09-27')->addDays($offset)->toDateString(), 'hit '.sprintf('%02d', $offset));
        }

        $component = $this->attentionComponent();

        $this->assertCount(12, $this->allRows($component));
        $component->assertSee('hit 00')->assertSee('hit 07')->assertDontSee('hit 08')->assertSeeHtml('fi-pagination');

        $component->call('setPage', 2)->assertSee('hit 08')->assertSee('hit 11')->assertDontSee('hit 07');

        $component->call('setPage', 99)->assertSee('hit 11')->assertDontSee('hit 07');
        $component->call('setPage', -4)->assertSee('hit 00')->assertDontSee('hit 08');
    }

    public function test_every_row_is_reachable_through_pagination_whatever_the_size(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['type' => 'action']);

        foreach (range(1, 62) as $n) {
            $this->hit($rule, '2026-10-09', 'bulk '.sprintf('%03d', $n));
        }

        $component = $this->attentionComponent();
        $paginator = $component->viewData('paginator');

        $this->assertSame(62, $component->instance()->total);
        $this->assertSame(62, $paginator->total());
        $this->assertSame(8, $paginator->lastPage());
        $this->assertCount(62, $this->allRows($component));

        $component->call('setPage', 8);
        $this->assertCount(6, $component->viewData('paginator')->items());
        $component->assertSee('bulk 062');
    }

    public function test_module_chips_count_the_loaded_rows_filter_toggle_and_reset_to_page_one(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view', 'shipment.view']);
        $rule = $this->rule($user, ['type' => 'action']);

        foreach (range(1, 9) as $n) {
            $this->hit($rule, '2026-10-09', 'request '.$n);
        }
        $this->hit($rule, '2026-10-10', 'only shipment', Shipment::class);

        $request = PurchaseRequest::class;
        $shipment = Shipment::class;
        $component = $this->attentionComponent();

        $counts = collect($component->instance()->modules)->pluck('count', 'module')->all();
        $this->assertSame([$request => 9, $shipment => 1], $counts);

        $component->call('setPage', 2)->call('filterModule', $shipment);
        $this->assertSame(1, $component->instance()->getPage());
        $component->assertSee('only shipment')->assertDontSee('request 1');

        $component->call('filterModule', $shipment)->assertSet('module', '')->assertSee('request 1');
        $component->call('filterModule', $request)->assertSee('request 1')->assertDontSee('only shipment');
    }

    public function test_past_heads_up_hits_are_excluded_while_action_hits_stay(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $headsUp = $this->rule($user);
        $action = $this->rule($user, ['type' => 'action']);
        $this->hit($headsUp, '2026-10-02', 'past heads up');
        $this->hit($action, '2026-10-05', 'past action');
        $this->hit($headsUp, '2026-10-12', 'future heads up');

        $component = $this->attentionComponent();

        $this->assertSame(['past action', 'future heads up'], $this->renderedLabels($component));
        $component->assertDontSee('past heads up');
    }

    public function test_a_user_without_the_module_view_permission_sees_none_of_that_module(): void
    {
        $rule = $this->sharedRule();
        $this->hit($rule, '2026-10-12', 'permitted request');
        $this->hit($rule, '2026-10-14', 'forbidden shipment', Shipment::class);

        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);

        $component = $this->attentionComponent();
        $this->assertSame(['permitted request'], $this->renderedLabels($component));
        $component->assertDontSee('forbidden shipment');

        $user->roles->first()->givePermissionTo(Permission::firstOrCreate(['name' => 'shipment.view', 'guard_name' => 'web']));

        $this->assertSame(['permitted request', 'forbidden shipment'], $this->renderedLabels($this->attentionComponent()));
    }

    public function test_an_admin_without_the_module_view_permission_sees_none_of_it_either(): void
    {
        $rule = $this->sharedRule();
        $this->hit($rule, '2026-10-14', 'admin forbidden shipment', Shipment::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin_junior');
        $this->actingAs($admin);

        $role = Role::where('name', 'admin_junior')->first();
        $permission = Permission::firstOrCreate(['name' => 'shipment.view', 'guard_name' => 'web']);
        $hadPermission = $role->hasPermissionTo($permission);
        $role->revokePermissionTo($permission);

        $this->attentionComponent()->assertDontSee('admin forbidden shipment');

        $role->givePermissionTo($permission);
        $this->attentionComponent()->assertSee('admin forbidden shipment');

        if (! $hadPermission) {
            $role->revokePermissionTo($permission);
        }
    }

    public function test_the_empty_state_renders_when_nothing_needs_attention(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['type' => 'action']);
        $this->hit($rule, '2026-11-01', 'out of window');

        $this->attentionComponent()
            ->assertSee(__('resources/dashboard/strings.landing_page.attention.empty', ['month' => 'October 2026']))
            ->assertSee(__('resources/dashboard/strings.landing_page.attention.open_calendar'))
            ->assertDontSee('out of window');
    }

    public function test_row_links_point_to_the_record_and_the_footer_to_the_dashboard(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $record = PurchaseRequest::factory()->create();
        $rule = $this->rule($user, ['type' => 'action']);
        CalendarHit::factory()->create([
            'calendar_rule_id' => $rule->id,
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $record->id,
            'label' => 'linked request',
            'event_date' => '2026-10-08',
        ]);

        $this->attentionComponent()
            ->assertSeeHtml('href="'.CalendarModules::url($record).'"')
            ->assertSeeHtml('href="'.Dashboard::getUrl().'"');
    }

    public function test_subjects_are_eager_loaded_in_one_query_per_module_and_missing_records_have_no_link(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['type' => 'action']);

        foreach (PurchaseRequest::factory()->count(3)->create() as $record) {
            CalendarHit::factory()->create([
                'calendar_rule_id' => $rule->id,
                'subject_type' => PurchaseRequest::class,
                'subject_id' => $record->id,
                'label' => 'real '.$record->id,
                'event_date' => '2026-10-09',
            ]);
        }
        $this->nextSubjectId = 2000000000;
        $this->hit($rule, '2026-10-10', 'orphan');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $component = $this->attentionComponent();
        $queries = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $sql): bool => str_contains($sql, 'from `purchase_requests`'));
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
        $rows = collect($this->allRows($component));
        $this->assertNull($rows->firstWhere('label', 'orphan')['url']);
        $this->assertNotNull($rows->firstWhere('label', 'real '.PurchaseRequest::latest('id')->value('id'))['url']);
    }

    public function test_a_paused_rule_stays_off_the_list_and_the_total_and_same_day_hits_keep_a_stable_order(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $paused = $this->rule($user, ['is_active' => false]);
        $live = $this->rule($user);
        $this->hit($paused, '2026-10-10', 'paused rule hit');
        $this->hit($live, '2026-10-10', 'same day first');
        $this->hit($live, '2026-10-10', 'same day second');

        $component = $this->attentionComponent();

        $this->assertSame(['same day first', 'same day second'], $this->renderedLabels($component));
        $this->assertSame(2, $component->instance()->total);
    }

    public function test_another_users_private_rule_hits_are_hidden(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $private = $this->rule(User::factory()->create(), ['visibility' => 'me']);
        $this->hit($private, '2026-10-10', 'someone elses private');

        $this->attentionComponent()->assertDontSee('someone elses private');
    }

    public function test_each_row_explains_why_it_is_listed_and_a_private_rules_conditions_never_leak(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['type' => 'action', 'filters' => ['rules' => [['type' => 'pr_number', 'data' => ['operator' => 'equals', 'settings' => ['text' => 'ZZ1']]]]]]);
        $private = $this->rule(User::factory()->create(), ['visibility' => 'me', 'filters' => ['rules' => [['type' => 'pr_number', 'data' => ['operator' => 'equals', 'settings' => ['text' => 'SECRET9']]]]]]);
        $this->hit($rule, '2026-10-10', 'first');
        $this->hit($rule, '2026-10-11', 'second');
        $this->hit($private, '2026-10-10', 'concealed');

        $component = $this->attentionComponent();
        $rows = collect($this->allRows($component));

        $this->assertStringContainsString('ZZ1', $rows->firstWhere('label', 'first')['reason']);
        $this->assertStringContainsString(adaptiveDate('2026-10-11'), $rows->firstWhere('label', 'second')['reason']);
        $component->assertSeeHtml('title="'.e($rows->firstWhere('label', 'first')['reason_full']).'"')
            ->assertDontSee('SECRET9')
            ->assertDontSee('concealed');
    }

    public function test_the_tab_is_hidden_and_no_query_runs_without_any_viewable_module(): void
    {
        $none = User::factory()->create();
        $this->actingAs($none);
        $none->can('purchase_request.view'); // warm the permission gate so the zero below is the engine's, not Spatie's

        DB::flushQueryLog();
        DB::enableQueryLog();
        $modules = CalendarBoard::attentionModules($none);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $modules);
        $this->assertSame([], $queries);

        Livewire::test(LandingPage::class)->assertDontSee(__('resources/dashboard/strings.landing_page.attention.tab'));
        $this->warmedCacheKeys[] = 'dashboard_counts:'.$none->id;

        $viewable = $this->actingAsUserWithPermissions(['purchase_request.view']);

        $html = Livewire::test(LandingPage::class)->assertSee(__('resources/dashboard/strings.landing_page.attention.tab'))->html();
        $position = fn (string $id): int => (int) strrpos($html, "activeTab = '{$id}'");
        $positions = array_map($position, ['customize', 'workflow', 'attention', 'search', 'features']);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'Tab order: Customize, Workflow, Needs attention, Search, then the right-aligned Features.');
        $this->assertGreaterThan(0, $positions[0]);
        $this->warmedCacheKeys[] = 'dashboard_counts:'.$viewable->id;
    }

    public function test_module_chip_buttons_render_well_formed_wire_click_attributes_and_the_loader_runs_no_second_alpine(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view', 'shipment.view']);
        $rule = $this->rule($user, ['type' => 'action']);
        $this->hit($rule, '2026-10-09', 'a request');
        $this->hit($rule, '2026-10-10', 'a shipment', Shipment::class);

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$this->attentionComponent()->html());
        $calls = collect((new \DOMXPath($dom))->query('//button'))
            ->filter(fn (\DOMElement $button): bool => preg_match('/^att-(all|\d+)$/', $button->getAttribute('wire:key')) === 1)
            ->mapWithKeys(fn (\DOMElement $button): array => [$button->getAttribute('wire:key') => $button->getAttribute('wire:click')]);

        $this->assertSame("filterModule('')", $calls['att-all']);
        $this->assertCount(3, $calls);
        foreach ($calls->except('att-all') as $call) {
            $this->assertMatchesRegularExpression("/^filterModule\('[^']+'\)$/", $call);
        }

        $loader = file_get_contents(resource_path('js/alpine/loader.js'));
        $this->assertStringNotContainsString("from 'alpinejs'", $loader);
        $this->assertStringNotContainsString('Alpine.start()', $loader);
    }

    public function test_the_rendered_landing_page_pages_on_the_server_and_ships_no_row_payload_in_the_snapshot(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view', 'shipment.view']);
        $user->forceFill(['email' => 'attention'.uniqid().'@persolco.com'])->save();
        $rule = $this->rule($user, ['type' => 'action']);

        foreach (range(1, 9) as $n) {
            $this->hit($rule, '2026-10-09', 'request '.$n);
        }
        $this->hit($rule, '2026-10-10', 'only shipment', Shipment::class);
        $this->warmedCacheKeys[] = 'dashboard_counts:'.$user->id;

        $page = $this->get(route('filament.dashboard.pages.landing-page'))->assertOk()->getContent();
        preg_match('/<div[^>]*wire:snapshot="([^"]*)"[^>]*wire:id="([^"]*)"[^>]*>(?=(?:(?!wire:id=).)*?att-all)/s', $page, $match);
        $this->assertNotEmpty($match, 'attention component root with its snapshot is on the page');

        $snapshot = html_entity_decode($match[1], ENT_QUOTES);
        $data = json_decode($snapshot, true)['data'];

        $this->assertSame(10, $data['total']);
        $this->assertArrayNotHasKey('rows', $data);
        $this->assertStringNotContainsString('request 1', $snapshot);
        $this->assertStringNotContainsString('only shipment', $snapshot);
        $this->assertStringContainsString('fi-pagination', $page);
    }

    private function monthComponent(string $month, bool $jalali = false): Testable
    {
        session(['calendar_type' => $jalali ? 'jalali' : 'gregorian']);

        return $this->attentionComponent()->set('month', $month);
    }

    private function seedMonths(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view', 'shipment.view']);
        $action = $this->rule($user, ['type' => 'action']);
        $headsUp = $this->rule($user);
        $this->hit($action, '2026-08-15', 'august overdue');
        $this->hit($action, '2026-09-10', 'september overdue');
        $this->hit($headsUp, '2026-09-12', 'september heads up');
        $this->hit($action, '2026-10-02', 'october overdue');
        $this->hit($action, '2026-10-08', 'october today');
        $this->hit($headsUp, '2026-10-30', 'october heads up', Shipment::class);
        $this->hit($action, '2026-11-05', 'november one');
        $this->hit($headsUp, '2026-11-20', 'november two');
        $this->hit($headsUp, '2026-12-01', 'december one');
    }

    public function test_the_default_is_the_current_month_with_overdue_carried_over_from_earlier_months(): void
    {
        $this->seedMonths();
        $component = $this->attentionComponent();

        $this->assertSame(['august overdue', 'september overdue', 'october overdue', 'october today', 'october heads up'], $this->renderedLabels($component));
        $this->assertSame('2026-10-01', $component->instance()->month);
        $this->assertTrue($component->instance()->isCurrentMonth);
        $this->assertSame(5, $component->instance()->total);
        $component->assertSee('October 2026')->assertDontSee(__('resources/dashboard/strings.landing_page.attention.current_month'));
    }

    public function test_past_months_show_only_their_own_items_without_past_heads_up(): void
    {
        $this->seedMonths();
        $component = $this->monthComponent('2026-09-01');

        $this->assertSame(['september overdue'], $this->renderedLabels($component));
        $this->assertSame(1, $component->instance()->total);
        $this->assertFalse($component->instance()->isCurrentMonth);
        $component->assertSee('September 2026')->assertSee(__('resources/dashboard/strings.landing_page.attention.current_month'));
    }

    public function test_future_months_show_only_their_own_items_and_the_badge_matches_the_list_for_every_month(): void
    {
        $this->seedMonths();
        $expected = ['2026-08-01' => 1, '2026-09-01' => 1, '2026-10-01' => 5, '2026-11-01' => 2, '2026-12-01' => 1, '2027-01-01' => 0];

        foreach ($expected as $month => $count) {
            $component = $this->monthComponent($month);
            $this->assertCount($count, $this->allRows($component), $month);
            $this->assertSame($count, $component->instance()->total, $month);
        }

        $november = $this->monthComponent('2026-11-01');
        $this->assertSame(['november one', 'november two'], $this->renderedLabels($november));
        $this->assertSame([2, 2], collect($this->allRows($november))->pluck('tier')->all());
    }

    public function test_prev_and_next_navigate_gregorian_months_across_the_year_boundary_and_back_to_current(): void
    {
        $this->seedMonths();
        session(['calendar_type' => 'gregorian']);
        $component = $this->attentionComponent();

        $component->call('nextMonth')->call('nextMonth')->assertSet('month', '2026-12-01')->assertSee('December 2026');
        $component->call('nextMonth')->assertSet('month', '2027-01-01')->assertSee('January 2027');
        $component->call('prevMonth')->call('prevMonth')->call('prevMonth')->assertSet('month', '2026-10-01');
        $component->call('prevMonth')->assertSee('September 2026');
        $component->call('goToCurrentMonth')->assertSet('month', '2026-10-01');
    }

    public function test_jalali_months_follow_the_persian_month_boundaries_and_the_year_boundary(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $headsUp = $this->rule($user);
        $action = $this->rule($user, ['type' => 'action']);
        $this->hit($action, '2026-09-22', 'last day of shahrivar');
        $this->hit($action, '2026-09-23', 'first of mehr');
        $this->hit($headsUp, '2026-10-22', 'thirtieth of mehr');
        $this->hit($headsUp, '2026-10-23', 'first of aban');
        $this->hit($headsUp, '2027-03-20', 'last of esfand 1405');
        $this->hit($headsUp, '2027-03-21', 'first of farvardin 1406');

        $component = $this->monthComponent('2026-10-08', true);
        $this->assertSame('2026-09-23', $component->instance()->month);
        $this->assertSame(['last day of shahrivar', 'first of mehr', 'thirtieth of mehr'], $this->renderedLabels($component));
        $this->assertSame(CalendarRange::forMonth('2026-10-08', true)->label(), $component->instance()->monthLabel);

        $component->call('nextMonth')->assertSet('month', '2026-10-23');
        $this->assertSame(['first of aban'], $this->renderedLabels($component));
        $component->call('prevMonth')->call('prevMonth');
        $this->assertSame(['last day of shahrivar'], $this->renderedLabels($component));
        $this->assertSame(1, $component->instance()->total);

        $esfand = $this->monthComponent('2027-03-20', true);
        $this->assertSame(['last of esfand 1405'], $this->renderedLabels($esfand));
        $esfand->call('nextMonth');
        $this->assertSame(['first of farvardin 1406'], $this->renderedLabels($esfand));
        $this->assertSame('2027-03-21', $esfand->instance()->month);
    }

    public function test_jalali_current_month_carries_over_overdue_from_earlier_months(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $action = $this->rule($user, ['type' => 'action']);
        $this->hit($action, '2026-08-01', 'old overdue');
        $this->hit($action, '2026-09-30', 'mehr overdue');
        $this->hit($action, '2026-10-25', 'aban item');

        $this->assertSame(['old overdue', 'mehr overdue'], $this->renderedLabels($this->monthComponent('', true)));
        $this->assertSame(['aban item'], $this->renderedLabels($this->monthComponent('2026-10-23', true)));
    }

    public function test_a_forged_month_value_falls_back_to_the_current_month(): void
    {
        $this->seedMonths();

        foreach (['not-a-date', '2026-13-45', '2026-02-30', '<script>', '9999-99-99'] as $forged) {
            $component = $this->monthComponent($forged);
            $this->assertSame('2026-10-01', $component->instance()->month, $forged);
            $this->assertSame(5, $component->instance()->total, $forged);
        }
    }

    public function test_navigating_resets_the_page_and_drops_a_module_filter_the_month_cannot_satisfy(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view', 'shipment.view']);
        $rule = $this->rule($user, ['type' => 'action']);

        foreach (range(1, 9) as $n) {
            $this->hit($rule, '2026-10-20', 'request '.$n);
        }
        $this->hit($rule, '2026-11-10', 'november shipment', Shipment::class);
        $shipment = Shipment::class;
        $request = PurchaseRequest::class;

        $component = $this->attentionComponent()->call('setPage', 2);
        $this->assertSame(2, $component->instance()->getPage());
        $component->call('nextMonth');
        $this->assertSame(1, $component->instance()->getPage());
        $this->assertSame([$shipment => 1], collect($component->instance()->modules)->pluck('count', 'module')->all());

        $component->call('filterModule', $shipment)->assertSet('module', $shipment)->call('prevMonth');
        $this->assertSame([$request => 9], collect($component->instance()->modules)->pluck('count', 'module')->all());
        $component->assertSet('module', '')->assertSee('request 1');
    }

    public function test_module_chips_and_pagination_follow_the_selected_month(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['type' => 'action']);

        foreach (range(1, 10) as $n) {
            $this->hit($rule, '2026-11-10', 'nov '.sprintf('%02d', $n));
        }

        $component = $this->attentionComponent();
        $component->assertDontSeeHtml('fi-pagination');
        $component->call('nextMonth')->assertSeeHtml('fi-pagination')->assertSee('nov 01')->assertDontSee('nov 09');
        $component->call('setPage', 2)->assertSee('nov 09');
        $this->assertSame([CalendarModules::label(PurchaseRequest::class) => 10], collect($component->instance()->modules)->pluck('count', 'label')->all());
        $this->assertSame(10, $component->instance()->total);
    }

    public function test_the_empty_state_names_the_selected_month_and_the_navigator_stays_visible(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $this->attentionComponent()
            ->assertSee(__('resources/dashboard/strings.landing_page.attention.empty', ['month' => 'October 2026']))
            ->call('nextMonth')
            ->assertSee(__('resources/dashboard/strings.landing_page.attention.empty', ['month' => 'November 2026']))
            ->assertSeeHtml('wire:click="prevMonth"')
            ->assertSeeHtml('wire:click="nextMonth"')
            ->assertSeeHtml('wire:click="goToCurrentMonth"');
    }

    public function test_navigation_never_exposes_private_rules_or_unviewable_modules_in_any_month(): void
    {
        $shared = $this->sharedRule();
        $private = $this->rule(User::factory()->create(), ['visibility' => 'me']);
        $this->hit($shared, '2026-11-10', 'visible november');
        $this->hit($shared, '2026-11-11', 'forbidden shipment', Shipment::class);
        $this->hit($private, '2026-11-12', 'private november');
        $this->hit($private, '2026-08-12', 'private august');
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $component = $this->monthComponent('2026-11-01');
        $this->assertSame(['visible november'], $this->renderedLabels($component));
        $this->assertSame(1, $component->instance()->total);
        $this->assertSame([], $this->renderedLabels($this->monthComponent('2026-08-01')));
    }

    public function test_the_month_labels_and_navigator_titles_are_translated_in_persian_and_french(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        app()->setLocale('fr');
        session(['calendar_type' => 'gregorian']);
        $this->attentionComponent()->assertSee('Mois précédent')->assertSee('Mois suivant')->assertSee('octobre 2026');

        app()->setLocale('fa');
        session(['calendar_type' => 'jalali']);
        $component = $this->attentionComponent();
        $component->assertSee('ماه بعد')->assertSee(CalendarRange::forMonth('2026-10-08', true)->label());
        $component->call('nextMonth')->assertSee('ماه جاری');
    }

    private function seeded(): array
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view', 'shipment.view']);
        $action = $this->rule($user, ['type' => 'action']);
        $headsUp = $this->rule($user);
        $hits = [
            'overdue' => $this->hit($action, '2026-10-02', 'overdue item'),
            'today' => $this->hit($headsUp, '2026-10-08', 'today item'),
            'later' => $this->hit($headsUp, '2026-10-20', 'later item'),
            'ship' => $this->hit($headsUp, '2026-10-25', 'shipment item', Shipment::class),
            'next' => $this->hit($headsUp, '2026-11-10', 'november item'),
        ];

        return [$user, $hits];
    }

    private function markedRead(User $user, array $hits): void
    {
        app(CalendarSeen::class)->mark($user, $hits);
        CalendarSeen::flush();
    }

    public function test_the_defaults_are_unread_on_no_time_filter_and_no_module_filter_with_five_ordered_chips(): void
    {
        [$user, $hits] = $this->seeded();
        $this->markedRead($user, [$hits['today']]);

        $component = $this->attentionComponent()->assertSet('unread', true)->assertSet('time', '')->assertSet('module', '');

        $this->assertSame(['overdue item', 'later item', 'shipment item'], $this->renderedLabels($component));
        $this->assertSame(3, $component->instance()->total);
        $component->assertDontSee('today item');

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$component->html());
        $chips = collect($dom->getElementsByTagName('button'))->filter(fn (\DOMElement $button): bool => in_array($button->getAttribute('wire:key'), ['att-time-previous', 'att-time-upcoming', 'att-unread', 'att-hidden', 'att-show-all'], true))->values();
        $this->assertSame(['att-time-previous', 'att-time-upcoming', 'att-unread', 'att-hidden', 'att-show-all'], $chips->map->getAttribute('wire:key')->all());
        $this->assertSame(["setTime('previous')", "setTime('upcoming')", 'toggleUnread', 'showHidden', 'showAll'], $chips->map->getAttribute('wire:click')->all());
        $this->assertSame(['false', 'false', 'true', 'false', 'false'], $chips->map->getAttribute('aria-pressed')->all());
    }

    public function test_the_hidden_chip_lists_only_hidden_items_and_unhiding_drops_them_from_it(): void
    {
        [$user, $hits] = $this->seeded();
        $this->markedRead($user, [$hits['today'], $hits['later']]);

        $component = $this->attentionComponent()->call('showHidden')->assertSet('hiddenOnly', true)->assertSet('unread', false);
        $this->assertSame(['today item', 'later item'], $this->renderedLabels($component));
        $this->assertSame(2, $component->instance()->total);
        $component->assertSeeHtml('aria-pressed="true"')->assertDontSee('overdue item');

        $component->call('setTime', 'previous');
        $this->assertSame([], $this->renderedLabels($component));
        $component->assertSee(__('resources/dashboard/strings.landing_page.attention.empty_filtered', ['month' => 'October 2026']));

        $component->call('setTime', 'previous')->call('markUnread', $hits['later']->id);
        $this->assertSame(['today item'], $this->renderedLabels($component));
        $this->assertSame(1, $component->instance()->total);

        $component->call('toggleUnread')->assertSet('hiddenOnly', false)->assertSet('unread', true);
        $this->assertNotContains('today item', $this->renderedLabels($component));
        $component->call('showHidden')->call('showHidden')->assertSet('hiddenOnly', false)->assertSet('unread', true);
        $component->call('showHidden')->call('showAll')->assertSet('hiddenOnly', false)->assertSet('unread', false);
    }

    public function test_previous_and_upcoming_switch_each_other_off_and_clear_on_a_second_click(): void
    {
        $this->seeded();

        $component = $this->attentionComponent()->call('setTime', 'previous');
        $this->assertSame(['overdue item'], $this->renderedLabels($component));
        $component->call('setTime', 'upcoming')->assertSet('time', 'upcoming');
        $this->assertSame(['today item', 'later item', 'shipment item'], $this->renderedLabels($component));
        $component->call('setTime', 'upcoming')->assertSet('time', '');
        $this->assertCount(4, $this->allRows($component));
        $component->call('setTime', 'forged')->assertSet('time', '');
    }

    public function test_unread_toggles_and_all_clears_both_filters_showing_read_and_unread(): void
    {
        [$user, $hits] = $this->seeded();
        $this->markedRead($user, [$hits['overdue']]);

        $component = $this->attentionComponent()->call('setTime', 'previous');
        $this->assertSame([], $this->renderedLabels($component));
        $component->call('toggleUnread')->assertSet('unread', false);
        $this->assertSame(['overdue item'], $this->renderedLabels($component));

        $component->call('toggleUnread')->call('setTime', 'upcoming')->call('showAll')->assertSet('unread', false)->assertSet('time', '');
        $this->assertCount(4, $this->allRows($component));
        $this->assertSame(4, $component->instance()->total);
    }

    public function test_filters_combine_with_months_modules_and_the_badge_matches_the_list_everywhere(): void
    {
        [$user, $hits] = $this->seeded();
        $this->markedRead($user, [$hits['later'], $hits['next']]);
        $shipment = Shipment::class;

        foreach ([false, true] as $jalali) {
            foreach (['2026-10-01', '2026-11-01', '2026-09-01'] as $month) {
                foreach ([true, false] as $unread) {
                    foreach (['', 'previous', 'upcoming'] as $time) {
                        $component = $this->monthComponent($month, $jalali);
                        if (! $unread) {
                            $component->call('toggleUnread');
                        }
                        if ($time !== '') {
                            $component->call('setTime', $time);
                        }
                        $this->assertSame(count($this->allRows($component)), $component->instance()->total, "$month $jalali $unread $time");
                    }
                }
            }
        }

        session(['calendar_type' => 'gregorian']);
        $component = $this->attentionComponent()->call('toggleUnread')->call('setTime', 'upcoming')->call('filterModule', $shipment);
        $component->assertSee('shipment item')->assertDontSee('later item');
        $this->assertSame([$shipment => 1], collect($component->instance()->modules)->pluck('count', 'module')->only($shipment)->all());

        $component->call('nextMonth')->assertSet('time', 'upcoming')->assertSet('unread', false);
        $this->assertSame(['november item'], $this->renderedLabels($component));
        $component->assertSet('module', '');
    }

    public function test_marking_one_stamps_the_viewer_only_hides_it_under_unread_and_shows_it_dimmed_under_all(): void
    {
        [$user, $hits] = $this->seeded();
        $other = User::factory()->create();
        Carbon::setTestNow(Carbon::parse('2026-10-08 14:30:00'));

        $component = $this->attentionComponent()->call('markRead', $hits['later']->id);
        $component->assertDispatched('calendar-read-changed')->assertNotDispatched('databaseNotificationsSent');
        CalendarSeen::flush();

        $this->assertSame([$hits['later']->id], app(CalendarSeen::class)->readIds($user));
        $this->assertSame(now()->getTimestamp(), app(CalendarSeen::class)->seenAt($user, $hits['later']->id)->getTimestamp());
        $this->assertSame([], app(CalendarSeen::class)->readIds($other));
        $this->assertNotContains('later item', $this->renderedLabels($component));
        $this->assertSame(3, $component->instance()->total);

        $component->call('showAll');
        $row = collect($this->allRows($component))->firstWhere('label', 'later item');
        $this->assertTrue($row['read']);
        $this->assertSame(__('resources/dashboard/strings.landing_page.attention.seen', ['when' => adaptiveDate(Carbon::parse('2026-10-08 14:30:00'), true)]), $row['seen']);
        $component->assertSee(__('resources/dashboard/strings.landing_page.attention.mark_unread'))->assertSeeHtml('opacity-60');
    }

    public function test_hidden_lines_never_show_under_not_hidden_and_show_again_unhides_the_item(): void
    {
        [$user, $hits] = $this->seeded();
        $this->markedRead($user, [$hits['later']]);

        $component = $this->attentionComponent()->assertDontSee('Hidden · ');
        $component->call('showAll')->assertSee('Hidden · ');
        $component->call('markUnread', $hits['later']->id);
        CalendarSeen::flush();

        $this->assertSame([], app(CalendarSeen::class)->readIds($user));
        $component->assertDontSee('Hidden · ')->call('toggleUnread');
        $this->assertContains('later item', $this->renderedLabels($component));
    }

    public function test_mark_all_on_this_page_marks_only_the_visible_page_and_the_page_is_clamped(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['type' => 'action']);

        foreach (range(0, 8) as $offset) {
            $this->hit($rule, Carbon::parse('2026-10-09')->addDays($offset)->toDateString(), 'hit '.$offset);
        }

        $component = $this->attentionComponent()->assertSee(__('resources/dashboard/strings.landing_page.attention.mark_page_read'));
        $component->call('markPageRead');
        CalendarSeen::flush();
        $this->assertCount(8, app(CalendarSeen::class)->readIds($user));
        $this->assertSame(['hit 8'], $this->renderedLabels($component));
        $this->assertSame(1, $component->instance()->total);

        $component->call('showAll')->call('setPage', 2);
        $this->assertSame(2, $component->instance()->getPage());
        $component->call('toggleUnread')->call('markPageRead');
        $this->assertSame(1, $component->instance()->getPage());
    }

    public function test_forged_ids_and_hits_the_viewer_cannot_see_are_ignored_and_a_moved_item_reappears_unread(): void
    {
        [$user, $hits] = $this->seeded();
        $private = $this->rule(User::factory()->create(), ['visibility' => 'me']);
        $secret = $this->hit($private, '2026-10-10', 'secret');
        $forbidden = $this->hit($this->sharedRule(), '2026-10-12', 'hidden module', \App\Models\Payment::class);

        $component = $this->attentionComponent();
        foreach ([$secret->id, $forbidden->id, 999999999, -3, 0] as $id) {
            $component->call('markRead', $id);
        }
        CalendarSeen::flush();
        $this->assertSame([], app(CalendarSeen::class)->readIds($user));

        $component->call('markRead', $hits['later']->id);
        CalendarHit::query()->whereKey($hits['later']->id)->update(['event_date' => '2026-10-21']);
        CalendarSeen::flush();
        $this->assertContains('later item', $this->renderedLabels($this->attentionComponent()));
    }

    public function test_marking_the_last_row_of_a_module_clears_that_module_filter(): void
    {
        [$user, $hits] = $this->seeded();
        $shipment = Shipment::class;

        $component = $this->attentionComponent()->call('filterModule', $shipment)->assertSet('module', $shipment);
        $component->call('markRead', $hits['ship']->id)->assertSet('module', '');
        $this->assertContains('later item', $this->renderedLabels($component));
    }

    public function test_hiding_cards_never_touches_a_bell_alert_and_the_bells_own_seen_hides_nothing(): void
    {
        [$user, $hits] = $this->seeded();
        $alert = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => CalendarAlertNotification::class,
            'data' => ['hit_ids' => [$hits['later']->id, $hits['today']->id]],
        ]);

        $component = $this->attentionComponent()->call('markRead', $hits['later']->id)->call('markRead', $hits['today']->id);
        $this->assertNull($alert->fresh()->read_at);

        $second = $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => CalendarAlertNotification::class, 'data' => ['hit_ids' => [$hits['overdue']->id]]]);
        $second->markAsRead();
        CalendarSeen::flush();
        $this->assertNotContains($hits['overdue']->id, app(CalendarSeen::class)->readIds($user));
        $this->assertContains('overdue item', $this->renderedLabels($this->attentionComponent()));
    }

    public function test_the_row_and_bulk_buttons_render_well_formed_wire_click_attributes(): void
    {
        [$user, $hits] = $this->seeded();
        $this->markedRead($user, [$hits['later']]);

        $html = $this->attentionComponent()->call('showAll')->html();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $calls = collect($dom->getElementsByTagName('button'))->map->getAttribute('wire:click')->filter();

        $this->assertContains('markPageRead', $calls->all());
        $this->assertSame(3, $calls->filter(fn (string $call): bool => preg_match('/^markRead\(\d+\)$/', $call) === 1)->count());
        $this->assertSame(1, $calls->filter(fn (string $call): bool => preg_match('/^markUnread\(\d+\)$/', $call) === 1)->count());
        $this->assertStringContainsString('aria-label="Hide"', $html);
    }

    public function test_the_empty_state_respects_the_filters_and_the_chips_stay_so_they_can_be_cleared(): void
    {
        [$user, $hits] = $this->seeded();
        $this->markedRead($user, array_values($hits));

        $component = $this->attentionComponent()
            ->assertSee(__('resources/dashboard/strings.landing_page.attention.empty', ['month' => 'October 2026']))
            ->assertSeeHtml('wire:click="showAll"');
        $component->call('setTime', 'previous')->assertSee(__('resources/dashboard/strings.landing_page.attention.empty_filtered', ['month' => 'October 2026']));
        $component->call('showAll')->assertSee('overdue item')->assertDontSee(__('resources/dashboard/strings.landing_page.attention.empty_filtered', ['month' => 'October 2026']));
    }

    public function test_the_filter_labels_are_translated_in_persian_and_french(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        app()->setLocale('fr');
        $this->attentionComponent()->assertSee('Passés')->assertSee('À venir')->assertSee('Non masqués')->assertSee('Tous');
        app()->setLocale('fa');
        $this->attentionComponent()->assertSee('گذشته')->assertSee('پیش‌رو')->assertSee('پنهان‌نشده')->assertSee('همه');
    }
}
