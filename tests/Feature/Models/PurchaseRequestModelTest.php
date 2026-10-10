<?php

namespace Tests\Feature\Models;

use App\Models\Attachment;
use App\Models\Custom;
use App\Models\Department;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Services\SmartCacheManager;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseRequestModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        SmartCacheManager::invalidate('Status');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        SmartCacheManager::invalidate('Status');
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

    private function prStatus(string $englishName): Status
    {
        return Status::factory()->create([
            'type' => PurchaseRequest::TYPE_PURCHASE_REQUEST,
            'english_type' => PurchaseRequest::TYPE_PURCHASE_REQUEST,
            'name' => $englishName,
            'english_name' => $englishName,
        ]);
    }

    private function itemStatus(string $englishName): Status
    {
        return Status::factory()->create([
            'type' => 'Purchase Item Status',
            'english_type' => 'Purchase Item Status',
            'name' => $englishName,
            'english_name' => $englishName,
        ]);
    }

    public function test_status_relation_is_scoped_to_purchase_request_status_type(): void
    {
        $wrongTypeStatus = Status::factory()->create([
            'type' => 'Something Else',
            'english_type' => 'Something Else',
        ]);
        // created (not updated) with the wrong-type status from the start, so this
        // purely exercises the status() relation's query scope, not the status-change Observer.
        $pr = PurchaseRequest::factory()->create(['status_id' => $wrongTypeStatus->id]);

        $this->assertNull($pr->fresh()->status);
    }

    public function test_observer_does_not_crash_when_updated_status_is_type_mismatched(): void
    {
        $underReview = $this->prStatus('Under Review');
        $wrongTypeStatus = Status::factory()->create([
            'type' => 'Something Else',
            'english_type' => 'Something Else',
        ]);

        $pr = PurchaseRequest::factory()->create(['status_id' => $underReview->id]);

        $pr->update(['status_id' => $wrongTypeStatus->id]);

        $this->assertNull($pr->fresh()->status);
    }

    public function test_status_relation_resolves_when_english_type_matches(): void
    {
        $status = $this->prStatus('Under Review');
        $pr = PurchaseRequest::factory()->create(['status_id' => $status->id]);

        $this->assertSame($status->id, $pr->fresh()->status->id);
    }

    public function test_formatted_name_shows_authorized_check_mark_and_pr_number(): void
    {
        app()->setLocale('en');
        $status = $this->prStatus('Authorized');
        $requester = User::factory()->create(['name' => 'Jane Requester']);
        $dept = Department::factory()->create(['english_name' => 'Procurement']);

        $pr = PurchaseRequest::factory()->create([
            'status_id' => $status->id,
            'requester_id' => $requester->id,
            'department_id' => $dept->id,
            'cost_center_id' => null,
        ]);
        $pr->load('status', 'requester', 'department', 'costCenter');

        // pr_number is force-regenerated on create by CodeGeneratingObserver regardless
        // of the factory's value, so assert against whatever it actually ended up as.
        $this->assertStringContainsString('✅', $pr->formatted_name_without_date);
        $this->assertStringContainsString($pr->pr_number, $pr->formatted_name_without_date);
        $this->assertStringContainsString('Procurement', $pr->formatted_name_without_date);
    }

    public function test_formatted_name_falls_back_to_department_when_cost_center_is_null(): void
    {
        app()->setLocale('en');
        $status = $this->prStatus('Under Review');
        $dept = Department::factory()->create(['english_name' => 'Logistics']);

        $pr = PurchaseRequest::factory()->create([
            'status_id' => $status->id,
            'cost_center_id' => null,
            'department_id' => $dept->id,
        ]);
        $pr->load('status', 'requester', 'department', 'costCenter');

        $this->assertStringNotContainsString('✅', $pr->formatted_name_without_date);
        $this->assertStringContainsString('Logistics', $pr->formatted_name_without_date);
    }

    public function test_formatted_name_with_date_prefers_required_by_date_label_over_last_edited(): void
    {
        app()->setLocale('en');
        $status = $this->prStatus('Under Review');
        $pr = PurchaseRequest::factory()->create([
            'status_id' => $status->id,
            'required_by_date' => now()->addDays(5),
        ]);
        $pr->load('status', 'requester', 'department', 'costCenter');

        $this->assertStringContainsString('Required by', $pr->formatted_name);
        $this->assertStringNotContainsString('Last Edited', $pr->formatted_name);
    }

    public function test_search_all_scope_matches_pr_number(): void
    {
        // pr_number is force-regenerated on create by CodeGeneratingObserver, so search
        // by whatever it actually ended up as rather than a value we tried to set.
        $target = PurchaseRequest::factory()->create();
        $term = substr($target->pr_number, -4);

        $results = PurchaseRequest::searchAll($term)->get();

        $this->assertTrue($results->contains('id', $target->id));
    }

    public function test_search_all_scope_matches_requester_name(): void
    {
        $requester = User::factory()->create(['name' => 'Unique Searchable Name']);
        $target = PurchaseRequest::factory()->create(['requester_id' => $requester->id]);
        PurchaseRequest::factory()->create();

        $results = PurchaseRequest::searchAll('Unique Searchable')->get();

        $this->assertCount(1, $results);
        $this->assertSame($target->id, $results->first()->id);
    }

    public function test_search_all_scope_with_blank_term_returns_query_unmodified(): void
    {
        // the dev DB already carries real rows, so assert the delta, not an absolute count.
        $before = PurchaseRequest::count();
        PurchaseRequest::factory()->count(3)->create();

        $this->assertSame($before + 3, PurchaseRequest::searchAll('   ')->count());
    }

    public function test_total_estimated_cost_is_cast_to_decimal_string(): void
    {
        $pr = PurchaseRequest::factory()->create(['total_estimated_cost' => 1234.5]);

        $this->assertSame('1234.50000', $pr->fresh()->total_estimated_cost);
    }

    public function test_soft_delete_removes_from_default_query_but_keeps_row(): void
    {
        $pr = PurchaseRequest::factory()->create();
        $pr->delete();

        $this->assertNull(PurchaseRequest::find($pr->id));
        $this->assertNotNull(PurchaseRequest::withTrashed()->find($pr->id));
        $this->assertTrue(PurchaseRequest::withTrashed()->find($pr->id)->trashed());
    }

    public function test_observer_cascades_authorized_status_to_child_items(): void
    {
        $underReview = $this->prStatus('Under Review');
        $authorized = $this->prStatus('Authorized');
        $itemUnderReview = $this->itemStatus('Under Review');
        $itemAuthorized = $this->itemStatus('Authorized');

        $pr = PurchaseRequest::factory()->create(['status_id' => $underReview->id]);
        $item = $pr->items()->create([
            'product_id' => Product::factory()->create()->id,
            'quantity' => 2,
            'estimated_cost' => 10,
            'status_id' => $itemUnderReview->id,
        ]);

        $pr->update(['status_id' => $authorized->id]);

        $this->assertSame('Authorized', $item->fresh()->status->english_name);
    }

    public function test_observer_does_not_cascade_when_status_moves_to_a_non_actionable_state(): void
    {
        $underReview = $this->prStatus('Under Review');
        $conditional = $this->prStatus('Conditional');
        $itemUnderReview = $this->itemStatus('Under Review');

        $pr = PurchaseRequest::factory()->create(['status_id' => $underReview->id]);
        $item = $pr->items()->create([
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
            'estimated_cost' => 5,
            'status_id' => $itemUnderReview->id,
        ]);

        $pr->update(['status_id' => $conditional->id]);

        $this->assertSame($itemUnderReview->id, $item->fresh()->status_id);
    }

    public function test_updating_status_id_writes_a_status_history_row_with_from_to_and_actor(): void
    {
        $underReview = $this->prStatus('Under Review');
        $authorized = $this->prStatus('Authorized');
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $pr = PurchaseRequest::factory()->create(['status_id' => $underReview->id]);
        $pr->update(['status_id' => $authorized->id]);

        $history = $pr->statusHistories()->latest('id')->first();

        $this->assertSame('status_id', $history->field);
        $this->assertSame($underReview->id, $history->from_status_id);
        $this->assertSame($authorized->id, $history->to_status_id);
        $this->assertSame($actor->id, $history->user_id);
    }

    public function test_transitioning_into_the_real_terminal_status_archives_all_attachments_in_one_query(): void
    {
        $nonTerminal = Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)
            ->whereNotNull('stage_order')
            ->orderBy('stage_order')
            ->first();
        $terminal = Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)
            ->whereNotNull('stage_order')
            ->orderByDesc('stage_order')
            ->first();

        $pr = PurchaseRequest::factory()->create(['status_id' => $nonTerminal->id]);
        $uploaded = Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_UPLOADED);
        $attachment = Attachment::factory()->forAttachable($pr)->create(['status_id' => $uploaded->id]);

        DB::enableQueryLog();
        $pr->update(['status_id' => $terminal->id]);
        $archiveQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'update `attachments`') && str_contains($q['query'], 'status_id'));
        DB::disableQueryLog();

        $this->assertCount(1, $archiveQueries);
        $this->assertSame('Archived', $attachment->fresh()->status->english_name);
    }

    public function test_transitioning_into_a_non_terminal_status_leaves_attachments_untouched(): void
    {
        $first = Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)
            ->whereNotNull('stage_order')
            ->orderBy('stage_order')
            ->first();
        $second = Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)
            ->whereNotNull('stage_order')
            ->orderBy('stage_order')
            ->skip(1)
            ->first();

        $pr = PurchaseRequest::factory()->create(['status_id' => $first->id]);
        $uploaded = Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_UPLOADED);
        $attachment = Attachment::factory()->forAttachable($pr)->create(['status_id' => $uploaded->id]);

        $pr->update(['status_id' => $second->id]);

        $this->assertSame('Uploaded', $attachment->fresh()->status->english_name);
    }

    public function test_observer_does_not_cascade_when_a_non_status_field_changes(): void
    {
        $status = $this->prStatus('Authorized');
        $itemStatus = $this->itemStatus('Under Review');

        $pr = PurchaseRequest::factory()->create(['status_id' => $status->id]);
        $item = $pr->items()->create([
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
            'estimated_cost' => 5,
            'status_id' => $itemStatus->id,
        ]);

        $pr->update(['notes' => 'just a note change']);

        $this->assertSame($itemStatus->id, $item->fresh()->status_id);
    }

    public function test_purchase_order_payments_match_the_manual_chain_and_ignore_registered_order_payments(): void
    {
        $pr = PurchaseRequest::factory()->create();
        $po = PurchaseOrder::factory()->create();
        $ro = RegisteredOrder::factory()->create();
        $pr->purchaseOrders()->attach($po);
        $pr->registeredOrders()->attach($ro);
        $viaPo = Payment::factory()->create(['targetable_type' => PurchaseOrder::class, 'targetable_id' => $po->id]);
        Payment::factory()->create(['targetable_type' => RegisteredOrder::class, 'targetable_id' => $ro->id]);
        Payment::factory()->create(['targetable_type' => PurchaseOrder::class, 'targetable_id' => PurchaseOrder::factory()->create()->id]);

        $manual = $pr->purchaseOrders->flatMap->payments->pluck('id')->sort()->values()->all();

        $this->assertSame([$viaPo->id], $manual);
        $this->assertSame($manual, $pr->purchaseOrderPayments->pluck('id')->sort()->values()->all());
        $this->assertSame($manual, PurchaseRequest::with('purchaseOrderPayments')->find($pr->id)->purchaseOrderPayments->pluck('id')->sort()->values()->all());
    }

    public function test_shipments_and_customs_match_the_manual_chain_through_registered_orders(): void
    {
        $pr = PurchaseRequest::factory()->create();
        $ro = RegisteredOrder::factory()->create();
        $other = RegisteredOrder::factory()->create();
        $pr->registeredOrders()->attach($ro);
        $shipment = Shipment::factory()->create(['registered_order_id' => $ro->id]);
        Shipment::factory()->create(['registered_order_id' => $other->id]);
        $custom = Custom::factory()->create(['registered_order_id' => $ro->id, 'shipment_id' => $shipment->id]);
        Custom::factory()->create(['registered_order_id' => $other->id]);

        $this->assertSame($pr->registeredOrders->flatMap->shipments->pluck('id')->sort()->values()->all(), $pr->shipments->pluck('id')->sort()->values()->all());
        $this->assertSame($pr->registeredOrders->flatMap->customs->pluck('id')->sort()->values()->all(), $pr->customs->pluck('id')->sort()->values()->all());
        $this->assertSame([$shipment->id], $pr->shipments->pluck('id')->all());
        $this->assertSame([$custom->id], $pr->customs->pluck('id')->all());
    }
}
