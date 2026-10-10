<?php

namespace Tests\Feature\Jobs;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\Visibility;
use App\Jobs\ExportCalendarHits;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportCalendarHitsTest extends TestCase
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
        Notification::fake();
        Queue::fake(); // observers are live: never let a dispatch run inline under QUEUE_CONNECTION=sync
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CalendarHit::flushVisibleRuleIds();
        DB::rollBack();
        parent::tearDown();
    }

    public function test_handle_writes_only_hits_visible_to_the_user_and_sends_a_download_link(): void
    {
        $user = $this->userWithPermissions(['purchase_request.view']);
        $other = User::factory()->create();
        $visibleRule = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'Visible Rule']);
        $hiddenRule = CalendarRule::factory()->create(['user_id' => $other->id, 'name' => 'Hidden Rule']);
        $visibleHit = CalendarHit::factory()->create(['calendar_rule_id' => $visibleRule->id, 'label' => 'VISIBLE-PR-1', 'event_date' => '2026-10-20']);
        $hiddenHit = CalendarHit::factory()->create(['calendar_rule_id' => $hiddenRule->id, 'label' => 'HIDDEN-PR-2', 'event_date' => '2026-10-20']);

        (new ExportCalendarHits([$visibleHit->id, $hiddenHit->id], $user->id, 'en'))->handle();

        $files = Storage::disk('local')->files("exports/{$user->id}");
        $this->assertNotEmpty($files);

        $csv = Storage::disk('local')->get($files[0]);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('VISIBLE-PR-1', $csv);
        $this->assertStringContainsString('Visible Rule', $csv);
        $this->assertStringNotContainsString('HIDDEN-PR-2', $csv);
        $this->assertStringNotContainsString('Hidden Rule', $csv);

        Notification::assertSentTo($user, DatabaseNotification::class, function (DatabaseNotification $notification): bool {
            $data = $notification->toArray(null);

            return $data['title'] === __('filament-actions::export.notifications.completed.title')
                && str_contains((string) $data['body'], '1');
        });
    }

    public function test_handle_never_trusts_a_visibility_memo_left_by_an_earlier_job_in_the_same_worker(): void
    {
        $user = $this->userWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => User::factory()->create()->id, 'visibility' => Visibility::EVERYONE->value]);
        $hit = CalendarHit::factory()->create(['calendar_rule_id' => $rule->id, 'label' => 'STALE-PR-9', 'event_date' => '2026-10-20']);

        (new ExportCalendarHits([$hit->id], $user->id, 'en'))->handle();
        $this->assertStringContainsString('STALE-PR-9', $this->exportedCsv($user));

        DB::table('calendar_rules')->where('id', $rule->id)->update(['visibility' => Visibility::ME->value]);
        (new ExportCalendarHits([$hit->id], $user->id, 'en'))->handle();

        $this->assertStringNotContainsString('STALE-PR-9', $this->exportedCsv($user));
    }

    public function test_handle_with_an_empty_or_forged_id_list_exports_no_rows(): void
    {
        $user = $this->userWithPermissions(['purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => User::factory()->create()->id]);
        $hit = CalendarHit::factory()->create(['calendar_rule_id' => $rule->id, 'label' => 'FORGED-PR-8']);

        (new ExportCalendarHits([], $user->id, 'en'))->handle();
        $this->assertStringNotContainsString('FORGED-PR-8', $this->exportedCsv($user));

        (new ExportCalendarHits([$hit->id, 0, 999999999], $user->id, 'en'))->handle();
        $this->assertStringNotContainsString('FORGED-PR-8', $this->exportedCsv($user));
    }

    public function test_handle_exports_nothing_without_the_module_view_permission(): void
    {
        $plain = User::factory()->create();
        $rule = CalendarRule::factory()->create(['visibility' => Visibility::EVERYONE->value, 'user_id' => User::factory()->create()->id]);
        $hit = CalendarHit::factory()->create(['calendar_rule_id' => $rule->id, 'label' => 'NOVIEW-PR-3', 'subject_type' => (new PurchaseRequest)->getMorphClass()]);

        (new ExportCalendarHits([$hit->id], $plain->id, 'en'))->handle();

        $files = Storage::disk('local')->files("exports/{$plain->id}");
        $this->assertNotEmpty($files);

        $csv = Storage::disk('local')->get($files[0]);
        $this->assertStringNotContainsString('NOVIEW-PR-3', $csv);

        Notification::assertSentTo($plain, DatabaseNotification::class, function (DatabaseNotification $notification): bool {
            return str_contains((string) $notification->toArray(null)['body'], '0');
        });
    }

    public function test_handle_reports_the_failure_without_a_download_link(): void
    {
        $user = $this->userWithPermissions(['purchase_request.view']);
        $hit = CalendarHit::factory()->create(['calendar_rule_id' => CalendarRule::factory()->create(['user_id' => $user->id])->id]);

        $file = config('app.name').'-'.strtoupper(class_basename(CalendarHit::class)).'-'.now()->format('His').'.csv';
        Storage::disk('local')->makeDirectory("exports/{$user->id}/{$file}");

        (new ExportCalendarHits([$hit->id], $user->id, 'en'))->handle();

        Notification::assertSentTo($user, DatabaseNotification::class, function (DatabaseNotification $notification): bool {
            return $notification->toArray(null)['title'] === __('resources/general/strings.export.error');
        });
    }

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

    private function exportedCsv(User $user): string
    {
        $files = Storage::disk('local')->files("exports/{$user->id}");

        return Storage::disk('local')->get(end($files));
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
}
