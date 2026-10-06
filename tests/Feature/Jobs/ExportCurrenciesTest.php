<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ExportCurrencies;
use App\Models\Currency;
use App\Models\User;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportCurrenciesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        Notification::fake();
    }

    protected function tearDown(): void
    {
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

    public function test_handle_writes_the_file_and_sends_a_success_notification_with_a_download_link(): void
    {
        $user = User::factory()->create();
        $record = Currency::factory()->create();

        $job = new ExportCurrencies([$record->id], $user->id, 'en');
        $job->handle();

        $files = Storage::disk('local')->files("exports/{$user->id}");
        $this->assertNotEmpty($files);

        $csv = Storage::disk('local')->get($files[0]);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString($record->english_name, $csv);

        Notification::assertSentTo($user, DatabaseNotification::class, function (DatabaseNotification $notification) use ($user) {
            $data = $notification->toArray(null);

            return $data['status'] === 'success'
                && str_contains($data['body'], '1')
                && str_contains($data['actions'][0]['url'], '/exports/'.$user->id.'/');
        });
    }

    public function test_handle_writes_an_empty_csv_and_still_succeeds_when_no_records_match(): void
    {
        $user = User::factory()->create();
        $record = Currency::factory()->create();
        $record->delete();
        $record->forceDelete();

        $job = new ExportCurrencies([$record->id], $user->id, 'en');
        $job->handle();

        Notification::assertSentTo($user, DatabaseNotification::class, function (DatabaseNotification $notification) {
            return $notification->toArray(null)['status'] === 'success';
        });
    }

    public function test_handle_sends_a_failure_notification_when_the_export_write_fails(): void
    {
        $user = User::factory()->create();
        $record = Currency::factory()->create();

        $disk = Storage::disk('local');
        $disk->delete("exports/{$user->id}");
        $disk->put("exports/{$user->id}", 'a file occupying the export directory path');

        $job = new ExportCurrencies([$record->id], $user->id, 'en');
        $job->handle();

        $disk->delete("exports/{$user->id}");

        Notification::assertSentTo($user, DatabaseNotification::class, function (DatabaseNotification $notification) {
            return $notification->toArray(null)['status'] === 'danger';
        });
    }
}
