<?php

namespace Tests\Feature\Models;

use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShipmentModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
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

    // TracksStatusHistory — 5 status columns, each tracked independently

    public function test_status_history_columns_lists_all_five_shipment_status_columns(): void
    {
        $this->assertSame(
            ['status_id', 'container_status_id', 'operation_status_id', 'shipment_status_id', 'doc_status_id'],
            Shipment::statusHistoryColumns()
        );
    }

    public function test_updating_status_id_writes_a_status_history_row_with_from_to_and_actor(): void
    {
        $from = Status::factory()->create();
        $to = Status::factory()->create();
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $shipment = Shipment::factory()->create(['status_id' => $from->id]);
        $shipment->update(['status_id' => $to->id]);

        $history = $shipment->statusHistories()->where('field', 'status_id')->latest('id')->first();

        $this->assertSame($from->id, $history->from_status_id);
        $this->assertSame($to->id, $history->to_status_id);
        $this->assertSame($actor->id, $history->user_id);
    }

    public function test_updating_two_different_status_columns_writes_two_distinct_history_rows(): void
    {
        $fromContainer = Status::factory()->create();
        $toContainer = Status::factory()->create();
        $toDoc = Status::factory()->create();

        $shipment = Shipment::factory()->create(['container_status_id' => $fromContainer->id]);
        $shipment->update(['container_status_id' => $toContainer->id, 'doc_status_id' => $toDoc->id]);

        $containerHistory = $shipment->statusHistories()->where('field', 'container_status_id')->latest('id')->first();
        $docHistory = $shipment->statusHistories()->where('field', 'doc_status_id')->latest('id')->first();

        $this->assertSame($fromContainer->id, $containerHistory->from_status_id);
        $this->assertSame($toContainer->id, $containerHistory->to_status_id);
        $this->assertSame($toDoc->id, $docHistory->to_status_id);
    }
}
