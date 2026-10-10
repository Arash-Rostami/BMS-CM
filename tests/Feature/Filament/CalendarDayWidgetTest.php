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
            ->assertSeeHtml(CalendarModules::url($request))
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
}
