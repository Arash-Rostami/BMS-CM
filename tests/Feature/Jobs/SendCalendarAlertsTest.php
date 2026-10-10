<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RebuildCalendarHits;
use App\Jobs\SendCalendarAlerts;
use App\Jobs\SendCalendarRuleAlerts;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Services\Calendar\CalendarAlerts;
use App\Services\Calendar\Sync\CalendarRouter;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendCalendarAlertsTest extends TestCase
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

    public function test_handle_fans_out_one_alert_job_per_active_rule(): void
    {
        $active = CalendarRule::factory()->create();
        $inactive = CalendarRule::factory()->create(['is_active' => false]);
        Queue::fake();

        (new SendCalendarAlerts)->handle(app(CalendarAlerts::class));

        Queue::assertPushed(SendCalendarRuleAlerts::class, 1);
        Queue::assertPushed(SendCalendarRuleAlerts::class, fn (SendCalendarRuleAlerts $job): bool => $job->ruleId === $active->id && $job->day === '2026-10-08');
        Queue::assertNotPushed(SendCalendarRuleAlerts::class, fn (SendCalendarRuleAlerts $job): bool => $job->ruleId === $inactive->id);
    }

    public function test_the_job_uses_the_configured_queue_which_defaults_to_the_standard_one(): void
    {
        $this->assertSame('default', config('calendar.queue'));
        $this->assertSame(config('calendar.queue'), (new SendCalendarAlerts)->queue);
    }

    public function test_the_scheduler_registers_the_daily_calendar_jobs(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn (Event $event): array => [$event->getSummaryForDisplay() => $event]);

        $this->assertSame('0 7 * * *', $events[SendCalendarAlerts::class]->expression);
        $this->assertSame('0 2 * * *', $events[RebuildCalendarHits::class]->expression);

        foreach ($events as $event) {
            $this->assertTrue($event->withoutOverlapping, 'A daily calendar job lost its withoutOverlapping guard — a slow run overlaps the next one.');
        }
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
