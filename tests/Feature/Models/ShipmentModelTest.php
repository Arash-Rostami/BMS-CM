<?php

namespace Tests\Feature\Models;

use App\Models\Company;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
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

    // HasPartSelection

    public function test_get_part_data_reports_used_available_and_next_free_part(): void
    {
        $ro = RegisteredOrder::factory()->create();
        Shipment::factory()->create(['registered_order_id' => $ro->id, 'contract_no' => 'CT-PARTS-001', 'part' => '1']);
        Shipment::factory()->create(['registered_order_id' => $ro->id, 'contract_no' => 'CT-PARTS-001', 'part' => '2']);

        $data = Shipment::getPartData($ro->id, 'CT-PARTS-001');

        $this->assertContains('1', $data['used']);
        $this->assertContains('2', $data['used']);
        $this->assertNotContains(1, $data['available']);
        $this->assertNotContains(2, $data['available']);
        $this->assertContains(3, $data['available']);
        $this->assertSame(3, $data['next']);
    }

    public function test_get_part_data_excludes_the_given_records_own_part_when_editing(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $editing = Shipment::factory()->create(['registered_order_id' => $ro->id, 'contract_no' => 'CT-PARTS-002', 'part' => '1']);

        $data = Shipment::getPartData($ro->id, 'CT-PARTS-002', $editing->id);

        $this->assertSame(1, $data['next']);
    }

    public function test_saving_a_shipment_busts_the_cached_part_data_for_its_order_and_contract(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $cacheKey = "shipment_parts_{$ro->id}_CT-PARTS-003";

        Shipment::getPartData($ro->id, 'CT-PARTS-003');
        $this->assertTrue(Cache::has($cacheKey));

        Shipment::factory()->create(['registered_order_id' => $ro->id, 'contract_no' => 'CT-PARTS-003', 'part' => '5']);

        $this->assertFalse(Cache::has($cacheKey));
    }

    // HasFormattedName

    public function test_formatted_name_attribute_includes_shipment_no_carrier_and_eta_in_english(): void
    {
        app()->setLocale('en');
        $carrier = Company::factory()->create(['english_name' => 'Formatted Carrier']);
        $shipment = Shipment::factory()->create(['company_id' => $carrier->id, 'eta' => '2026-02-01']);
        Shipment::whereKey($shipment->id)->update(['shipment_no' => 'SHP-FMT-001']);
        $shipment->refresh();

        $this->assertStringContainsString('SHP-FMT-001', $shipment->formatted_name);
        $this->assertStringContainsString('Formatted Carrier', $shipment->formatted_name);
        $this->assertStringContainsString('ETA', $shipment->formatted_name);
    }

    public function test_formatted_name_without_date_attribute_omits_the_eta(): void
    {
        app()->setLocale('en');
        $carrier = Company::factory()->create(['english_name' => 'Formatted Carrier']);
        $shipment = Shipment::factory()->create(['company_id' => $carrier->id, 'eta' => '2026-02-01']);
        Shipment::whereKey($shipment->id)->update(['shipment_no' => 'SHP-FMT-002']);
        $shipment->refresh();

        $this->assertStringContainsString('SHP-FMT-002', $shipment->formatted_name_without_date);
        $this->assertStringNotContainsString('ETA', $shipment->formatted_name_without_date);
    }

    // HasSearchableRelations

    public function test_scope_search_all_matches_the_shipments_own_identifier_columns(): void
    {
        $target = Shipment::factory()->create(['bl_number' => 'BL-SEARCHALL-001']);
        $other = Shipment::factory()->create();

        $results = Shipment::query()->searchAll('SEARCHALL')->get();

        $this->assertTrue($results->contains('id', $target->id));
        $this->assertFalse($results->contains('id', $other->id));
    }

    public function test_scope_search_all_matches_the_related_registered_orders_ro_number(): void
    {
        $ro = RegisteredOrder::factory()->create();
        RegisteredOrder::whereKey($ro->id)->update(['ro_number' => 'RO-SEARCHALL-001']);
        $target = Shipment::factory()->create(['registered_order_id' => $ro->id]);
        $other = Shipment::factory()->create();

        $results = Shipment::query()->searchAll('RO-SEARCHALL-001')->get();

        $this->assertTrue($results->contains('id', $target->id));
        $this->assertFalse($results->contains('id', $other->id));
    }

    // HasDocumentChecklist contract

    public function test_document_checklist_reads_items_from_a_wrapped_docs_array(): void
    {
        $shipment = Shipment::factory()->create(['docs' => ['items' => [['name' => 'bl', 'received' => true]]]]);

        $this->assertSame([['name' => 'bl', 'received' => true]], $shipment->documentChecklist());
    }

    public function test_is_document_tracking_enabled_defaults_true_when_no_track_entry_exists(): void
    {
        $shipment = Shipment::factory()->create(['docs' => ['items' => [['name' => 'bl', 'received' => true]]]]);

        $this->assertTrue($shipment->isDocumentTrackingEnabled());
    }

    public function test_is_document_tracking_enabled_reflects_the_tracks_received_flag(): void
    {
        $shipment = Shipment::factory()->create(['docs' => ['items' => [['name' => 'track', 'received' => false]]]]);

        $this->assertFalse($shipment->isDocumentTrackingEnabled());
    }

    public function test_set_document_checklist_persists_rows_quietly(): void
    {
        $shipment = Shipment::factory()->create(['docs' => []]);

        $shipment->setDocumentChecklist([['name' => 'ci', 'received' => true]]);

        $this->assertSame([['name' => 'ci', 'received' => true]], $shipment->fresh()->docs);
    }
}
