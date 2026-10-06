<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ExportPayments;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use App\Models\User;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportPaymentsTest extends TestCase
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
        $record = Payment::factory()->create();

        $job = new ExportPayments([$record->id], $user->id, 'en');
        $job->handle();

        $files = Storage::disk('local')->files("exports/{$user->id}");
        $this->assertNotEmpty($files);

        $csv = Storage::disk('local')->get($files[0]);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString($record->payment_no, $csv);

        Notification::assertSentTo($user, DatabaseNotification::class, function (DatabaseNotification $notification) use ($user) {
            $data = $notification->toArray(null);

            return $data['status'] === 'success'
                && str_contains($data['body'], '1')
                && str_contains($data['actions'][0]['url'], '/exports/'.$user->id.'/');
        });
    }

    public function test_handle_resolves_the_morphed_targetable_into_the_export_row(): void
    {
        $user = User::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create();
        $registeredOrder = RegisteredOrder::factory()->create();

        $purchaseOrderPayment = Payment::factory()->forTargetable($purchaseOrder)->create();
        $registeredOrderPayment = Payment::factory()->forTargetable($registeredOrder)->create();

        $job = new ExportPayments([$purchaseOrderPayment->id, $registeredOrderPayment->id], $user->id, 'en');
        $job->handle();

        $csv = Storage::disk('local')->get(Storage::disk('local')->files("exports/{$user->id}")[0]);
        $this->assertStringContainsString($purchaseOrder->po_number, $csv);
        $this->assertStringContainsString($registeredOrder->ro_number, $csv);
    }

    public function test_handle_neutralizes_leading_formula_characters_in_free_text_fields(): void
    {
        $user = User::factory()->create();
        $record = Payment::factory()->create(['notes' => '=cmd|/c calc']);

        $job = new ExportPayments([$record->id], $user->id, 'en');
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
        $record = Payment::factory()->create();

        $updater = User::factory()->create(['name' => '+SUM(1,2)']);
        $this->actingAs($updater);
        $record->update(['notes' => 'triggers updated_by_id']);

        $job = new ExportPayments([$record->id], $exportingUser->id, 'en');
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
        $record = Payment::factory()->create();
        $record->delete();
        $record->forceDelete();

        $job = new ExportPayments([$record->id], $user->id, 'en');
        $job->handle();

        Notification::assertSentTo($user, DatabaseNotification::class, function (DatabaseNotification $notification) {
            return $notification->toArray(null)['status'] === 'success';
        });
    }
}