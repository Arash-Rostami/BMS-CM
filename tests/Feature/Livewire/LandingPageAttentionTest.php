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
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\Sync\CalendarEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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
     * @return array<int, string>
     */
    private function renderedLabels(Testable $component): array
    {
        return collect($component->instance()->rows)->pluck('label')->all();
    }

    public function test_rows_are_ordered_overdue_today_then_next_seven_days(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['type' => 'action']);
        $this->hit($rule, '2026-10-01', 'oldest overdue');
        $this->hit($rule, '2026-10-05', 'newer overdue');
        $this->hit($rule, '2026-10-08', 'due today');
        $this->hit($rule, '2026-10-11', 'due soon');
        $this->hit($rule, '2026-10-15', 'due last');
        $this->hit($rule, '2026-10-20', 'far future');

        $component = $this->attentionComponent();
        $rows = $component->instance()->rows;

        $this->assertSame(
            ['oldest overdue', 'newer overdue', 'due today', 'due soon', 'due last'],
            $this->renderedLabels($component)
        );
        $this->assertTrue($rows[0]['overdue']);
        $this->assertTrue($rows[1]['overdue']);
        $this->assertFalse($rows[2]['overdue']);
        $this->assertFalse($rows[4]['overdue']);
        $component->assertDontSee('far future');
    }

    public function test_the_list_is_capped_at_ten_rows(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['type' => 'action']);

        foreach (range(0, 11) as $offset) {
            $this->hit($rule, Carbon::parse('2026-09-27')->addDays($offset)->toDateString(), 'hit '.$offset);
        }

        $component = $this->attentionComponent();

        $this->assertCount(10, $component->instance()->rows);
        $this->assertSame('hit 0', $this->renderedLabels($component)[0]);
        $this->assertSame('hit 9', $this->renderedLabels($component)[9]);
        $component->assertDontSee('hit 10')->assertDontSee('hit 11');
    }

    public function test_the_total_is_counted_and_the_capped_tail_is_announced(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user, ['type' => 'action']);

        foreach (range(0, 11) as $offset) {
            $this->hit($rule, Carbon::parse('2026-09-27')->addDays($offset)->toDateString(), 'hit '.$offset);
        }

        $component = $this->attentionComponent();

        $this->assertCount(10, $component->instance()->rows);
        $this->assertSame(12, $component->instance()->total);
        $component->assertSee(__('resources/dashboard/strings.landing_page.attention.more', ['count' => 2]))
            ->assertDontSee('hit 10')
            ->assertDontSee('hit 11');
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
            ->assertSee(__('resources/dashboard/strings.landing_page.attention.empty'))
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
        $rows = collect($component->instance()->rows);
        $this->assertNull($rows->firstWhere('label', 'orphan')['url']);
        $this->assertNotNull($rows->firstWhere('label', 'real '.PurchaseRequest::latest('id')->value('id'))['url']);
    }

    public function test_the_window_includes_today_plus_seven_and_excludes_today_plus_eight(): void
    {
        $user = $this->actingAsUserWithPermissions(['purchase_request.view']);
        $rule = $this->rule($user);
        $this->hit($rule, '2026-10-15', 'day seven');
        $this->hit($rule, '2026-10-16', 'day eight');

        $this->assertSame(['day seven'], $this->renderedLabels($this->attentionComponent()));
    }

    public function test_another_users_private_rule_hits_are_hidden(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);
        $private = $this->rule(User::factory()->create(), ['visibility' => 'me']);
        $this->hit($private, '2026-10-10', 'someone elses private');

        $this->attentionComponent()->assertDontSee('someone elses private');
    }

    public function test_the_tab_is_hidden_and_no_query_runs_without_any_viewable_module(): void
    {
        $none = User::factory()->create();
        $this->actingAs($none);
        $none->can('purchase_request.view'); // warm the permission gate so the zero below is the engine's, not Spatie's

        DB::flushQueryLog();
        DB::enableQueryLog();
        $hits = app(CalendarEngine::class)->attention($none);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $hits);
        $this->assertSame([], $queries);

        Livewire::test(LandingPage::class)->assertDontSee(__('resources/dashboard/strings.landing_page.attention.tab'));
        $this->warmedCacheKeys[] = 'dashboard_counts:'.$none->id;

        $viewable = $this->actingAsUserWithPermissions(['purchase_request.view']);

        $html = Livewire::test(LandingPage::class)->assertSee(__('resources/dashboard/strings.landing_page.attention.tab'))->html();
        $position = fn (string $id): int => (int) strpos($html, "activeTab = '{$id}'");
        $this->assertGreaterThan($position('search'), $position('attention'), 'Attention sits right after Search.');
        $this->assertLessThan($position('features'), $position('attention'), 'Attention comes before the right-aligned Features tab.');
        $this->warmedCacheKeys[] = 'dashboard_counts:'.$viewable->id;
    }
}
