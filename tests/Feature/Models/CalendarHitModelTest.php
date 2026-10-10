<?php

namespace Tests\Feature\Models;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\Visibility;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Calendar\Sync\CalendarRouter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CalendarHitModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        app()->setLocale('en');

        Carbon::setTestNow(Carbon::parse('2026-10-08'));
        CalendarHit::flushVisibleRuleIds();
        CalendarRouter::flushRoutes();
        Queue::fake(); // observers are live: never let a dispatch run inline under QUEUE_CONNECTION=sync
    }

    protected function tearDown(): void
    {
        CalendarHit::flushVisibleRuleIds();
        CalendarRouter::flushRoutes();
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

    private function hit(array $overrides = []): CalendarHit
    {
        return CalendarHit::factory()->create($overrides);
    }

    // Schema

    public function test_rule_subject_unique_index_exists(): void
    {
        $index = collect(Schema::getIndexes('calendar_hits'))
            ->firstWhere('name', 'calendar_hits_rule_subject_unique');

        $this->assertNotNull($index);
        $this->assertSame(['calendar_rule_id', 'subject_type', 'subject_id'], $index['columns']);
    }

    public function test_duplicate_rule_subject_row_is_rejected_by_the_unique_index(): void
    {
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $record = $this->hit();

        CalendarHit::create([
            'calendar_rule_id' => $record->calendar_rule_id,
            'subject_type' => $record->subject_type,
            'subject_id' => $record->subject_id,
            'label' => 'Duplicate',
            'event_date' => '2026-10-10',
            'alerts_sent' => [],
        ]);
    }

    // Casts

    public function test_date_and_json_casts_roundtrip(): void
    {
        $record = $this->hit([
            'event_date' => '2026-10-15',
            'alerts_sent' => ['7' => '2026-10-08'],
        ]);

        $record = $record->fresh();

        $this->assertTrue($record->event_date instanceof Carbon);
        $this->assertSame('2026-10-15', $record->event_date->toDateString());
        $this->assertSame(['7' => '2026-10-08'], $record->alerts_sent);
    }

    // Relations

    public function test_rule_and_subject_relations_resolve(): void
    {
        $subject = PurchaseRequest::factory()->create();
        $record = CalendarHit::factory()->forSubject($subject)->create();

        $record = $record->fresh();
        $record->load('rule');

        $this->assertTrue($record->rule instanceof CalendarRule);
        $this->assertSame($record->rule->id, $record->calendar_rule_id);
        $this->assertTrue($record->subject->is($subject));
    }

    // Scopes

    public function test_for_day_matches_only_that_date(): void
    {
        $inDay = $this->hit(['event_date' => '2026-10-15']);
        $other = $this->hit(['event_date' => '2026-10-16']);

        $results = CalendarHit::forDay(Carbon::parse('2026-10-15'))->pluck('id');

        $this->assertTrue($results->contains($inDay->id));
        $this->assertFalse($results->contains($other->id));
    }

    public function test_overdue_covers_action_hits_before_today_only(): void
    {
        $pastAction = $this->hit(['event_date' => '2026-10-01']);
        $pastAction->rule->update(['type' => RuleType::ACTION->value]);
        $todayAction = $this->hit(['event_date' => '2026-10-08']);
        $todayAction->rule->update(['type' => RuleType::ACTION->value]);
        $pastHeadsUp = $this->hit(['event_date' => '2026-10-01']);

        $results = CalendarHit::overdue()->pluck('id');

        $this->assertTrue($results->contains($pastAction->id));
        $this->assertFalse($results->contains($todayAction->id));
        $this->assertFalse($results->contains($pastHeadsUp->id));
    }

    public function test_not_past_heads_up_excludes_only_past_heads_up_rows(): void
    {
        $pastHeadsUp = $this->hit(['event_date' => '2026-10-01']);
        $todayHeadsUp = $this->hit(['event_date' => '2026-10-08']);
        $futureHeadsUp = $this->hit(['event_date' => '2026-10-20']);
        $pastAction = $this->hit(['event_date' => '2026-10-01']);
        $pastAction->rule->update(['type' => RuleType::ACTION->value]);

        $results = CalendarHit::notPastHeadsUp()->pluck('id');

        $this->assertFalse($results->contains($pastHeadsUp->id));
        $this->assertTrue($results->contains($todayHeadsUp->id));
        $this->assertTrue($results->contains($futureHeadsUp->id));
        $this->assertTrue($results->contains($pastAction->id));
    }

    public function test_factory_for_subject_state_stamps_the_morph_pair(): void
    {
        $subject = PurchaseRequest::factory()->create();
        $record = CalendarHit::factory()->forSubject($subject)->create();

        $this->assertSame($subject->getMorphClass(), $record->subject_type);
        $this->assertSame($subject->id, $record->subject_id);
    }

    // Visibility

    private function userWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin_junior');

        return $user;
    }

    public function test_visible_to_requires_both_module_permission_and_rule_visibility(): void
    {
        $permitted = $this->userWithPermissions(['purchase_request.view']);
        $unpermitted = $this->userWithPermissions(['shipment.view']);

        $shared = $this->hit(['event_date' => '2026-10-15']);
        $shared->rule->update(['visibility' => Visibility::EVERYONE->value]);
        $hiddenRule = $this->hit(['event_date' => '2026-10-15']);
        $hiddenRule->rule->update(['visibility' => Visibility::ME->value]);

        $permittedView = CalendarHit::visibleTo($permitted)->pluck('id');
        $unpermittedView = CalendarHit::visibleTo($unpermitted)->pluck('id');

        $this->assertTrue($permittedView->contains($shared->id));
        $this->assertFalse($permittedView->contains($hiddenRule->id));
        $this->assertFalse($unpermittedView->contains($shared->id));
    }

    public function test_visible_to_shows_admin_hits_only_with_module_permission(): void
    {
        $admin = $this->admin();

        $record = $this->hit(['event_date' => '2026-10-15']);
        $record->rule->update(['visibility' => Visibility::ME->value]);

        $role = Role::where('name', 'admin_junior')->first();
        $permission = Permission::firstOrCreate(['name' => 'purchase_request.view', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->assertTrue(CalendarHit::visibleTo($admin)->whereKey($record->id)->exists());

        $role->revokePermissionTo($permission);

        $this->assertFalse(CalendarHit::visibleTo($admin)->whereKey($record->id)->exists());
    }

    public function test_visible_to_reaches_shared_rule_hits_for_listed_users(): void
    {
        $viewer = $this->userWithPermissions(['purchase_request.view']);

        $record = $this->hit(['event_date' => '2026-10-15']);
        $record->rule->update(['visibility' => Visibility::USERS->value, 'shared_user_ids' => [$viewer->id]]);

        $this->assertTrue(CalendarHit::visibleTo($viewer)->whereKey($record->id)->exists());
    }
}
