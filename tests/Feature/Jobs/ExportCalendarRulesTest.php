<?php

namespace Tests\Feature\Jobs;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\Visibility;
use App\Jobs\ExportCalendarRules;
use App\Models\CalendarRule;
use App\Models\User;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportCalendarRulesTest extends TestCase
{
    private ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        DB::table('calendar_hits')->delete();
        DB::table('calendar_rules')->delete();
        app()->setLocale('en');
        Notification::fake();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        if ($this->user !== null) {
            Storage::disk('local')->deleteDirectory("exports/{$this->user->id}");
        }

        DB::rollBack();
        parent::tearDown();
    }

    public function test_handle_exports_only_rules_visible_to_the_user_and_sends_a_download_link(): void
    {
        $this->user = User::factory()->create();
        $mine = CalendarRule::factory()->create(['user_id' => $this->user->id, 'name' => 'MINE-RULE-1']);
        $shared = CalendarRule::factory()->create(['user_id' => User::factory()->create()->id, 'name' => 'SHARED-RULE-2', 'visibility' => Visibility::EVERYONE->value]);
        $hidden = CalendarRule::factory()->create(['user_id' => User::factory()->create()->id, 'name' => 'HIDDEN-RULE-3']);

        (new ExportCalendarRules([$mine->id, $shared->id, $hidden->id], $this->user->id, 'en'))->handle();

        $csv = $this->exportedCsv();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('MINE-RULE-1', $csv);
        $this->assertStringContainsString('SHARED-RULE-2', $csv);
        $this->assertStringNotContainsString('HIDDEN-RULE-3', $csv);

        Notification::assertSentTo($this->user, DatabaseNotification::class, function (DatabaseNotification $notification): bool {
            return $notification->toArray(null)['title'] === __('filament-actions::export.notifications.completed.title');
        });
    }

    public function test_handle_neutralises_spreadsheet_formulas_in_free_text(): void
    {
        $this->user = User::factory()->create();
        $rule = CalendarRule::factory()->create(['user_id' => $this->user->id, 'name' => '=HYPERLINK("http://evil")']);

        (new ExportCalendarRules([$rule->id], $this->user->id, 'en'))->handle();

        $this->assertStringContainsString("'=HYPERLINK", $this->exportedCsv());
    }

    public function test_handle_with_a_forged_id_list_exports_no_rows(): void
    {
        $this->user = User::factory()->create();
        $foreign = CalendarRule::factory()->create(['user_id' => User::factory()->create()->id, 'name' => 'FORGED-RULE-4']);

        (new ExportCalendarRules([$foreign->id, 0, 999999999], $this->user->id, 'en'))->handle();

        $this->assertStringNotContainsString('FORGED-RULE-4', $this->exportedCsv());
    }

    public function test_handle_reports_the_failure_without_a_download_link(): void
    {
        $this->user = User::factory()->create();
        $rule = CalendarRule::factory()->create(['user_id' => $this->user->id]);

        $file = config('app.name').'-'.strtoupper(class_basename(CalendarRule::class)).'-'.now()->format('His').'.csv';
        Storage::disk('local')->makeDirectory("exports/{$this->user->id}/{$file}");

        (new ExportCalendarRules([$rule->id], $this->user->id, 'en'))->handle();

        Notification::assertSentTo($this->user, DatabaseNotification::class, function (DatabaseNotification $notification): bool {
            return $notification->toArray(null)['title'] === __('resources/general/strings.export.error');
        });
    }

    private function exportedCsv(): string
    {
        $files = Storage::disk('local')->files("exports/{$this->user->id}");

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
