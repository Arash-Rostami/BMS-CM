<?php

namespace Tests\Feature\Traits;

use App\Models\Attachment;
use App\Models\Custom;
use App\Models\PurchaseRequest;
use App\Models\Status;
use App\Models\Traits\General\TracksStatusHistory;
use App\Services\SmartCacheManager;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App\Models\Traits\General\TracksStatusHistory boots created/updated hooks that
 * log an append-only StatusHistory row per tracked status column (modelsPattern.md
 * §6b), plus the withStatusHistoryReason() per-write reason bookkeeping and the
 * archiveAttachmentsIfTerminal() side effect. Composed on 8 unrelated models
 * (PurchaseRequest, ProformaInvoice is excluded, RegisteredOrder, PurchaseOrder,
 * Payment, Shipment, Custom, Correspondence, BankProfile) — cross-cutting, only
 * indirectly touched elsewhere. Uses PurchaseRequest (default single-column) and
 * Custom (multi-column override) as the two representative, unrelated consumers.
 */
class TracksStatusHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        SmartCacheManager::invalidate('Status');
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

    public function test_purchase_request_composes_the_trait(): void
    {
        $this->assertContains(TracksStatusHistory::class, class_uses_recursive(PurchaseRequest::class));
    }

    public function test_default_status_history_columns_is_status_id(): void
    {
        $this->assertSame(['status_id'], PurchaseRequest::statusHistoryColumns());
    }

    public function test_custom_overrides_status_history_columns_with_its_three_status_fks(): void
    {
        $this->assertSame(
            ['clearance_status_id', 'bank_guarantee_status_id', 'commitment_status_id'],
            Custom::statusHistoryColumns()
        );
    }

    public function test_creating_a_record_with_a_non_null_status_logs_an_initial_from_null_row(): void
    {
        $status = Status::factory()->create();
        $request = PurchaseRequest::factory()->create(['status_id' => $status->id]);

        $history = $request->statusHistories()->latest()->first();

        $this->assertNotNull($history);
        $this->assertSame('status_id', $history->field);
        $this->assertNull($history->from_status_id);
        $this->assertSame($status->id, $history->to_status_id);
    }

    public function test_updating_a_tracked_column_logs_a_from_to_row_only_for_the_column_that_changed(): void
    {
        $custom = Custom::factory()->create();
        $newClearance = Status::factory()->create();
        $originalClearanceId = $custom->clearance_status_id;
        $originalGuaranteeId = $custom->bank_guarantee_status_id;

        $custom->update(['clearance_status_id' => $newClearance->id]);

        $rows = $custom->statusHistories()->where('field', 'clearance_status_id')->get();
        $latest = $rows->sortByDesc('id')->first();

        $this->assertSame($originalClearanceId, $latest->from_status_id);
        $this->assertSame($newClearance->id, $latest->to_status_id);
        $this->assertSame(
            0,
            $custom->statusHistories()
                ->where('field', 'bank_guarantee_status_id')
                ->where('to_status_id', '!=', $originalGuaranteeId)
                ->count()
        );
    }

    public function test_saving_with_no_dirty_tracked_column_logs_no_additional_row(): void
    {
        $request = PurchaseRequest::factory()->create();
        $before = $request->statusHistories()->count();

        $request->notes = 'unrelated field changed';
        $request->save();

        $this->assertSame($before, $request->statusHistories()->count());
    }

    public function test_with_status_history_reason_attaches_the_reason_to_the_next_write_then_resets(): void
    {
        $request = PurchaseRequest::factory()->create();
        $next = Status::factory()->create();

        PurchaseRequest::withStatusHistoryReason('Needs more budget detail');
        $request->update(['status_id' => $next->id]);

        $reasoned = $request->statusHistories()->where('to_status_id', $next->id)->latest()->first();
        $this->assertSame('Needs more budget detail', $reasoned->reason);

        $anotherNext = Status::factory()->create();
        $request->update(['status_id' => $anotherNext->id]);

        $unreasoned = $request->statusHistories()->where('to_status_id', $anotherNext->id)->latest()->first();
        $this->assertNull($unreasoned->reason);
    }

    public function test_archive_attachments_if_terminal_is_a_no_op_for_a_non_terminal_status(): void
    {
        $type = 'Trait Test Terminal Type '.uniqid();
        $stage1 = Status::factory()->create(['type' => $type, 'english_type' => $type, 'stage_order' => 1]);
        $stage2 = Status::factory()->create(['type' => $type, 'english_type' => $type, 'stage_order' => 2]);
        Status::factory()->create(['type' => $type, 'english_type' => $type, 'stage_order' => 3]);

        $request = PurchaseRequest::factory()->create(['status_id' => $stage1->id]);
        $attachment = Attachment::factory()->forAttachable($request)->create(['status_id' => null]);

        $request->update(['status_id' => $stage2->id]);

        $this->assertNull($attachment->refresh()->status_id);
    }

    public function test_archive_attachments_if_terminal_bulk_archives_once_a_terminal_status_is_reached(): void
    {
        // Does not create its own "Archived"/"Attachment Status" fixture: Status::findBy()'s
        // plain ->first() can resolve to a real pre-existing dev-DB row sharing that
        // type/name instead of a fresh one (testPattern.md §0's "assert business identity,
        // not a specific row id" lesson) — so this asserts the resolved status's identity
        // and that both attachments land on the SAME status, not a specific fixture id.
        $type = 'Trait Test Terminal Type '.uniqid();
        $start = Status::factory()->create(['type' => $type, 'english_type' => $type, 'stage_order' => 1]);
        $terminal = Status::factory()->create(['type' => $type, 'english_type' => $type, 'stage_order' => 2]);
        Status::firstOrCreate(
            ['english_type' => \App\Models\Attachment::TYPE_ATTACHMENT, 'english_name' => \App\Models\Attachment::STATUS_ARCHIVED],
            ['type' => \App\Models\Attachment::TYPE_ATTACHMENT, 'name' => \App\Models\Attachment::STATUS_ARCHIVED]
        );

        $request = PurchaseRequest::factory()->create(['status_id' => $start->id]);
        $attachmentOne = Attachment::factory()->forAttachable($request)->create(['status_id' => null]);
        $attachmentTwo = Attachment::factory()->forAttachable($request)->create(['status_id' => null]);

        $request->update(['status_id' => $terminal->id]);

        $attachmentOne->refresh();
        $attachmentTwo->refresh();

        $this->assertNotNull($attachmentOne->status_id);
        $this->assertSame($attachmentOne->status_id, $attachmentTwo->status_id);
        $this->assertSame(\App\Models\Attachment::STATUS_ARCHIVED, $attachmentOne->status->english_name);
        $this->assertSame(\App\Models\Attachment::TYPE_ATTACHMENT, $attachmentOne->status->english_type);
    }
}
