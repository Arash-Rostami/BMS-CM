<?php

namespace Tests\Feature\Services\Calendar;

use App\Filament\Widgets\CalendarDayWidget;
use App\Filament\Widgets\CalendarGridWidget;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Calendar\Display\CalendarBoard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarPermissionTest extends TestCase
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

    private function userWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create();

        $role = Role::create(['name' => 'calendar_permission_test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);

        return $user;
    }

    /**
     * A rule the whole pipeline can see, owned by a third party so ownership never
     * explains visibility in the assertions below. The owner stays signed in — every
     * test re-signs in as the actual subject before asserting.
     */
    private function sharedRule(): CalendarRule
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        return CalendarRule::factory()->create(['user_id' => $owner->id, 'visibility' => 'everyone']);
    }

    private function hit(CalendarRule $rule, string $eventDate, string $subjectType, string $label): CalendarHit
    {
        return CalendarHit::factory()->create([
            'calendar_rule_id' => $rule->id,
            'subject_type' => $subjectType,
            'subject_id' => 1,
            'label' => $label,
            'event_date' => $eventDate,
        ]);
    }

    private function cell(array $cells, string $iso): array
    {
        return collect($cells)->firstWhere('iso', $iso);
    }

    public function test_a_user_without_the_module_view_permission_sees_none_of_it_in_grid_agenda_or_day(): void
    {
        $rule = $this->sharedRule();
        $this->hit($rule, '2026-10-15', PurchaseRequest::class, 'permitted request');
        $this->hit($rule, '2026-10-16', Shipment::class, 'forbidden shipment');

        $user = $this->userWithPermissions(['purchase_request.view']);
        $this->actingAs($user);

        $component = Livewire::test(CalendarGridWidget::class);
        $cells = $component->instance()->cells;

        $this->assertSame(1, $this->cell($cells, '2026-10-15')['count']);
        $this->assertSame(0, $this->cell($cells, '2026-10-16')['count']);
        $component->assertSee('permitted request')->assertDontSee('forbidden shipment');

        $dayRows = CalendarBoard::dayQuery($user, Carbon::parse('2026-10-16'), null, null)->get();
        $this->assertSame(0, $dayRows->count());

        // positive control: granting the module view reveals the same hit everywhere
        $role = $user->roles->first();
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'shipment.view', 'guard_name' => 'web']));

        $component = Livewire::test(CalendarGridWidget::class);
        $this->assertSame(1, $this->cell($component->instance()->cells, '2026-10-16')['count']);
        $component->assertSee('forbidden shipment');

        $this->assertSame(1, CalendarBoard::dayQuery($user, Carbon::parse('2026-10-16'), null, null)->count());
    }

    public function test_an_admin_without_the_module_view_permission_sees_none_of_it_either(): void
    {
        $rule = $this->sharedRule();
        $this->hit($rule, '2026-10-16', Shipment::class, 'admin forbidden shipment');

        $admin = User::factory()->create();
        $admin->assignRole('admin_junior');
        $this->actingAs($admin);

        $role = Role::where('name', 'admin_junior')->first();
        $permission = Permission::firstOrCreate(['name' => 'shipment.view', 'guard_name' => 'web']);
        $hadPermission = $role->hasPermissionTo($permission);
        $role->revokePermissionTo($permission);

        $component = Livewire::test(CalendarGridWidget::class);
        $this->assertSame(0, $this->cell($component->instance()->cells, '2026-10-16')['count']);
        $this->assertSame(0, CalendarBoard::dayQuery($admin, Carbon::parse('2026-10-16'), null, null)->count());

        // positive control: restoring the permission reveals the hit for the admin too
        $role->givePermissionTo($permission);

        $this->assertSame(1, $this->cell(Livewire::test(CalendarGridWidget::class)->instance()->cells, '2026-10-16')['count']);

        if (! $hadPermission) {
            $role->revokePermissionTo($permission);
        }
    }

    public function test_activity_landing_and_recipients_enforce_the_same_module_view_rule(): void
    {
        $rule = $this->sharedRule();
        $rule->update(['subject' => Shipment::class]);
        $this->hit($rule, '2026-10-09', Shipment::class, 'forbidden shipment');
        $user = $this->userWithPermissions(['purchase_request.view']);
        $shipment = Shipment::factory()->create();
        $this->actingAs($user);

        $this->assertSame([], \App\Filament\Actions\CalendarActivityAction::searchRecords($user, Shipment::class, (string) $shipment->shipment_no));
        $this->assertSame([], \App\Filament\Actions\CalendarActivityAction::history($user, Shipment::class, $shipment->id, true)['rows']);
        $this->assertSame(0, app(\App\Services\Calendar\Sync\CalendarEngine::class)->attention($user)->count());
        $this->assertFalse($rule->recipients()->contains('id', $user->id));

        $user->roles->first()->givePermissionTo(Permission::firstOrCreate(['name' => 'shipment.view', 'guard_name' => 'web']));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        CalendarHit::flushVisibleRuleIds();

        $this->assertSame(1, app(\App\Services\Calendar\Sync\CalendarEngine::class)->attention($user->fresh())->count());
        $this->assertTrue($rule->recipients()->contains('id', $user->id));
    }

    public function test_a_forged_deep_link_falls_back_to_safe_defaults_on_both_widgets(): void
    {
        $rule = $this->sharedRule();
        $this->hit($rule, '2026-10-15', PurchaseRequest::class, 'linked request');

        $private = CalendarRule::factory()->create(['visibility' => 'me']); // owned by the still-signed-in third party

        $user = $this->userWithPermissions(['purchase_request.view']);
        $this->actingAs($user);

        $grid = Livewire::withQueryParams([
            'cal_date' => '2026-10-15',
            'cal_rule' => (string) $private->id,
            'cal_module' => Shipment::class,
        ])->test(CalendarGridWidget::class);

        $this->assertSame('2026-10-15', $grid->get('selected'));
        $this->assertNull($grid->get('ruleId'));
        $this->assertNull($grid->get('module'));
        $this->assertSame(1, $this->cell($grid->instance()->cells, '2026-10-15')['count']);
        $grid->assertSuccessful();

        $day = Livewire::withQueryParams([
            'cal_date' => '2026-10-15',
            'cal_rule' => (string) $private->id,
            'cal_module' => Shipment::class,
        ])->test(CalendarDayWidget::class);

        $this->assertSame('2026-10-15', $day->get('date'));
        $this->assertNull($day->get('ruleId'));
        $this->assertNull($day->get('module'));
        $day->assertSee('linked request')->assertSuccessful();
    }
}
