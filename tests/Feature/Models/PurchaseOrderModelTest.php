<?php

namespace Tests\Feature\Models;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseOrderModelTest extends TestCase
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

    // total_amount / total_quantity — computed from items, not a DB column

    public function test_total_amount_sums_quantity_times_unit_price_across_items(): void
    {
        $po = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $po->id, 'quantity' => 3, 'unit_price' => 10]);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $po->id, 'quantity' => 2, 'unit_price' => 15]);

        $fresh = $po->fresh();

        $this->assertEquals(60, $fresh->total_amount);
        $this->assertEquals(5, $fresh->total_quantity);
    }

    public function test_total_amount_and_total_quantity_are_zero_without_items(): void
    {
        $po = PurchaseOrder::factory()->create();

        $this->assertEquals(0, $po->fresh()->total_amount);
        $this->assertEquals(0, $po->fresh()->total_quantity);
    }

    // formatted_name

    public function test_formatted_name_without_date_shows_creator_and_po_number(): void
    {
        app()->setLocale('en');
        $creator = User::factory()->create(['name' => 'Jane Buyer']);
        $po = PurchaseOrder::factory()->create(['user_id' => $creator->id]);
        $po->load('creator');

        $formatted = $po->formatted_name_without_date;

        $this->assertStringContainsString($po->po_number, $formatted);
        $this->assertStringContainsString('Jane Buyer', $formatted);
    }

    public function test_formatted_name_shows_approved_emoji_when_status_is_approved(): void
    {
        app()->setLocale('en');
        $status = Status::factory()->create(['english_type' => PurchaseOrder::TYPE_PURCHASE_ORDER, 'english_name' => 'Approved']);
        $po = PurchaseOrder::factory()->create(['status_id' => $status->id]);
        $po->load('status');

        $this->assertStringContainsString('✅', $po->formatted_name_without_date);
    }

    public function test_formatted_name_with_date_shows_valid_by_label_when_validity_date_present(): void
    {
        app()->setLocale('en');
        $po = PurchaseOrder::factory()->create(['validity_date' => now()->addDays(10)]);

        $this->assertStringContainsString('Valid by', $po->formatted_name);
    }

    public function test_formatted_name_with_date_falls_back_to_last_edited_label_without_validity_date(): void
    {
        // validity_date is NOT NULL in the real schema, so this exercises the fallback
        // branch via a bare unsaved instance rather than a persisted factory row.
        app()->setLocale('en');
        $po = new PurchaseOrder(['po_number' => 'PO-TEST']);
        $po->updated_at = now();

        $this->assertStringContainsString('Last Edited', $po->formatted_name);
    }

    // scopeSearchAll

    public function test_search_all_scope_matches_po_number(): void
    {
        $target = PurchaseOrder::factory()->create();
        $term = substr($target->po_number, -4);

        $results = PurchaseOrder::searchAll($term)->get();

        $this->assertTrue($results->contains('id', $target->id));
    }

    public function test_search_all_scope_with_blank_term_returns_query_unmodified(): void
    {
        $before = PurchaseOrder::count();
        PurchaseOrder::factory()->count(3)->create();

        $this->assertSame($before + 3, PurchaseOrder::searchAll('   ')->count());
    }

    // Soft delete

    public function test_soft_delete_removes_from_default_query_but_keeps_row(): void
    {
        $po = PurchaseOrder::factory()->create();
        $po->delete();

        $this->assertNull(PurchaseOrder::find($po->id));
        $this->assertTrue(PurchaseOrder::withTrashed()->find($po->id)->trashed());
    }

    // TracksStatusHistory

    public function test_updating_status_id_writes_a_status_history_row_with_from_to_and_actor(): void
    {
        $from = Status::factory()->create();
        $to = Status::factory()->create();
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $po = PurchaseOrder::factory()->create(['status_id' => $from->id]);
        $po->update(['status_id' => $to->id]);

        $history = $po->statusHistories()->latest('id')->first();

        $this->assertSame('status_id', $history->field);
        $this->assertSame($from->id, $history->from_status_id);
        $this->assertSame($to->id, $history->to_status_id);
        $this->assertSame($actor->id, $history->user_id);
    }
}
