<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RebuildCalendarHits;
use App\Jobs\SyncCalendarRule;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Services\Calendar\Sync\CalendarRouter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RebuildCalendarHitsTest extends TestCase
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
        CalendarRouter::flushRoutes();
        Queue::fake(); // observers are live: never let a dispatch run inline under QUEUE_CONNECTION=sync
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::rollBack();
        parent::tearDown();
    }

    public function test_handle_dispatches_a_sync_job_per_active_rule(): void
    {
        $activeA = CalendarRule::factory()->create();
        $activeB = CalendarRule::factory()->create();
        $inactive = CalendarRule::factory()->create(['is_active' => false]);
        Queue::fake();

        (new RebuildCalendarHits)->handle();

        Queue::assertPushed(SyncCalendarRule::class, 2);
        Queue::assertPushed(SyncCalendarRule::class, fn (SyncCalendarRule $job): bool => $job->ruleId === $activeA->id);
        Queue::assertPushed(SyncCalendarRule::class, fn (SyncCalendarRule $job): bool => $job->ruleId === $activeB->id);
        Queue::assertNotPushed(SyncCalendarRule::class, fn (SyncCalendarRule $job): bool => $job->ruleId === $inactive->id);
    }

    public function test_the_job_uses_the_configured_queue_which_defaults_to_the_standard_one(): void
    {
        $this->assertSame('default', config('calendar.queue'));
        $this->assertSame(config('calendar.queue'), (new RebuildCalendarHits)->queue);
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
