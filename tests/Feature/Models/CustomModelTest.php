<?php

namespace Tests\Feature\Models;

use App\Models\Custom;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomModelTest extends TestCase
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

    // TracksStatusHistory — 3 status columns, each tracked independently

    public function test_status_history_columns_lists_all_three_custom_status_columns(): void
    {
        $this->assertSame(
            ['clearance_status_id', 'bank_guarantee_status_id', 'commitment_status_id'],
            Custom::statusHistoryColumns()
        );
    }

    public function test_updating_clearance_status_id_writes_a_status_history_row_with_from_to_and_actor(): void
    {
        $from = Status::factory()->create();
        $to = Status::factory()->create();
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $custom = Custom::factory()->create(['clearance_status_id' => $from->id]);
        $custom->update(['clearance_status_id' => $to->id]);

        $history = $custom->statusHistories()->where('field', 'clearance_status_id')->latest('id')->first();

        $this->assertSame($from->id, $history->from_status_id);
        $this->assertSame($to->id, $history->to_status_id);
        $this->assertSame($actor->id, $history->user_id);
    }

    public function test_updating_two_different_status_columns_writes_two_distinct_history_rows(): void
    {
        $toBankGuarantee = Status::factory()->create();
        $toCommitment = Status::factory()->create();

        $custom = Custom::factory()->create();
        $custom->update(['bank_guarantee_status_id' => $toBankGuarantee->id, 'commitment_status_id' => $toCommitment->id]);

        $bankGuaranteeHistory = $custom->statusHistories()->where('field', 'bank_guarantee_status_id')->latest('id')->first();
        $commitmentHistory = $custom->statusHistories()->where('field', 'commitment_status_id')->latest('id')->first();

        $this->assertSame($toBankGuarantee->id, $bankGuaranteeHistory->to_status_id);
        $this->assertSame($toCommitment->id, $commitmentHistory->to_status_id);
    }
}
