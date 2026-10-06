<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ExportShipments;
use App\Models\Shipment;
use App\Models\User;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportShipmentsTest extends TestCase
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
        $record = Shipment::factory()->create();

        $job = new ExportShipments([$record->id], $user->id, 'en');
        $job->handle();

        $files = Storage::disk('local')->files("exports/{$user->id}");
        $this->assertNotEmpty($files);

        $csv = Storage::disk('local')->get($files[0]);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString($record->shipment_no, $csv);

        Notification::assertSentTo($user, DatabaseNotification::class, function (DatabaseNotification $notification) use ($user) {
            $data = $notification->toArray(null);

            return $data['status'] === 'success'
                && str_contains($data['body'], '1')
                && str_contains($data['actions'][0]['url'], '/exports/'.$user->id.'/');
        });
    }

    public function test_handle_writes_one_flat_row_per_record_with_no_item_rows(): void
    {
        $user = User::factory()->create();
        $record = Shipment::factory()->create();

        $job = new ExportShipments([$record->id], $user->id, 'en');
        $job->handle();

        $csv = Storage::disk('local')->get(Storage::disk('local')->files("exports/{$user->id}")[0]);
        $this->assertSame(2, substr_count($csv, "\n"));

        Notification::assertSentTo($user, DatabaseNotification::class, function (DatabaseNotification $notification) {
            return str_contains($notification->toArray(null)['body'], 'and 1 row(s)');
        });
    }

    public function test_handle_neutralizes_leading_formula_characters_in_free_text_fields(): void
    {
        $user = User::factory()->create();
        $record = Shipment::factory()->create(['notes' => '=cmd|/c calc']);

        $job = new ExportShipments([$record->id], $user->id, 'en');
        $job->handle();

        $csv = Storage::disk('local')->get(Storage::disk('local')->files("exports/{$user->id}")[0]);
        $this->assertStringNotContainsString(",=cmd", $csv);
        $this->assertStringContainsString(",\"'=cmd", $csv);
    }

    public function test_handle_neutralizes_formula_characters_in_creator_and_updater_names(): void
    {
        $exportingUser = User::factory()->create();

        $creator = User::factory()->create(['name' => '=1+1']);
        $this->actingAs($creator);
        $record = Shipment::factory()->create();

        $updater = User::factory()->create(['name' => '+SUM(1,2)']);
        $this->actingAs($updater);
        $record->update(['notes' => 'triggers updated_by_id']);

        $job = new ExportShipments([$record->id], $exportingUser->id, 'en');
        $job->handle();

        $csv = Storage::disk('local')->get(Storage::disk('local')->files("exports/{$exportingUser->id}")[0]);
        $this->assertStringNotContainsString(',=1+1', $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringNotContainsString(',+SUM(1,2)', $csv);
        $this->assertStringContainsString(",\"'+SUM(1,2)", $csv);
    }

    public function test_handle_writes_an_empty_csv_and_still_succeeds_when_no_records_match(): void
    {
        $user = User::factory()->create();
        $record = Shipment::factory()->create();
        $record->delete();
        $record->forceDelete();

        $job = new ExportShipments([$record->id], $user->id, 'en');
        $job->handle();

        Notification::assertSentTo($user, DatabaseNotification::class, function (DatabaseNotification $notification) {
            return $notification->toArray(null)['status'] === 'success';
        });
    }
}