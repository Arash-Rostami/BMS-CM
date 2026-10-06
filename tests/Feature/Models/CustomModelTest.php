<?php

namespace Tests\Feature\Models;

use App\Models\Custom;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
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

    // Constants

    public function test_scannable_table_identifier_and_status_type_constants_are_pinned(): void
    {
        $this->assertSame('customs', Custom::SCANNABLE_TABLE);
        $this->assertSame('custom_no', Custom::SCANNABLE_IDENTIFIER);
        $this->assertSame('Clearance Status', Custom::TYPE_CLEARANCE_STATUS);
        $this->assertSame('Guarantee Status', Custom::TYPE_BANK_GUARANTEE_STATUS);
        $this->assertSame('Commitment Fulfillment Status', Custom::TYPE_COMMITMENT_STATUS);
    }

    // HasFormattedName

    public function test_formatted_name_without_date_combines_emoji_declaration_no_and_shipment_number(): void
    {
        $shipment = Shipment::factory()->create();
        $custom = Custom::factory()->create([
            'shipment_id' => $shipment->id,
            'declaration_no' => 'DEC-FORMAT-1',
        ]);

        $this->assertSame(
            "🛃  DEC-FORMAT-1 ┆ {$shipment->shipment_no}",
            $custom->formatted_name_without_date
        );
    }

    public function test_formatted_name_falls_back_to_na_and_em_dash_when_declaration_no_and_shipment_are_missing(): void
    {
        $custom = new Custom(['declaration_no' => null]);

        $this->assertSame('🛃  N/A ┆ —', $custom->formatted_name_without_date);
    }

    public function test_formatted_name_includes_the_clearance_date_with_the_locale_appropriate_label(): void
    {
        $shipment = Shipment::factory()->create();
        $custom = Custom::factory()->create([
            'shipment_id' => $shipment->id,
            'declaration_no' => 'DEC-FORMAT-2',
            'clearance_date' => '2026-05-10',
        ]);

        app()->setLocale('en');
        $this->assertStringContainsString('Clearance Date', $custom->formatted_name);
        $this->assertStringContainsString(toGregorianDate($custom->clearance_date), $custom->formatted_name);

        app()->setLocale('fa');
        $this->assertStringContainsString('تاریخ ترخیص', $custom->formatted_name);
        app()->setLocale('en');
    }

    // HasSearchableRelations

    public function test_scope_search_all_matches_its_own_columns(): void
    {
        $target = Custom::factory()->create(['contract_no' => 'CT-SEARCHALL-1']);
        $other = Custom::factory()->create(['contract_no' => 'CT-SEARCHALL-2']);

        $results = Custom::query()->searchAll('CT-SEARCHALL-1')->pluck('id');

        $this->assertTrue($results->contains($target->id));
        $this->assertFalse($results->contains($other->id));
    }

    public function test_scope_search_all_matches_through_the_shipment_relation(): void
    {
        $shipment = Shipment::factory()->create(['bl_number' => 'BL-SEARCHALL-1']);
        $target = Custom::factory()->create(['shipment_id' => $shipment->id]);
        $other = Custom::factory()->create();

        $results = Custom::query()->searchAll('BL-SEARCHALL-1')->pluck('id');

        $this->assertTrue($results->contains($target->id));
        $this->assertFalse($results->contains($other->id));
    }

    public function test_scope_search_all_matches_through_the_registered_order_relation(): void
    {
        // ro_number is CodeGenerator-mapped and force-regenerated on create (same-day values are
        // prefix-related, e.g. RO-YYMMDD then RO-YYMMDD-1) — force a distinct value on the other
        // fixture via a raw update that bypasses the regenerating observer.
        $ro = RegisteredOrder::factory()->create();
        $target = Custom::factory()->create(['registered_order_id' => $ro->id]);

        $otherRo = RegisteredOrder::factory()->create();
        RegisteredOrder::whereKey($otherRo->id)->update(['ro_number' => 'RO-UNRELATED-999']);
        $other = Custom::factory()->create(['registered_order_id' => $otherRo->id]);

        $results = Custom::query()->searchAll($ro->ro_number)->pluck('id');

        $this->assertTrue($results->contains($target->id));
        $this->assertFalse($results->contains($other->id));
    }

    public function test_scope_search_all_returns_the_unfiltered_query_for_a_blank_term(): void
    {
        Custom::factory()->create();

        $this->assertSame(
            Custom::query()->count(),
            Custom::query()->searchAll('   ')->count()
        );
    }

    // Relationships — each status column is scoped to its own Status::english_type

    public function test_clearance_status_relation_is_scoped_to_its_own_status_type(): void
    {
        $wrongType = Status::factory()->create(['english_type' => Custom::TYPE_BANK_GUARANTEE_STATUS]);
        $custom = Custom::factory()->create(['clearance_status_id' => $wrongType->id]);

        $this->assertNull($custom->clearanceStatus()->first());
    }

    public function test_bank_guarantee_status_relation_is_scoped_to_its_own_status_type(): void
    {
        $wrongType = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS]);
        $custom = Custom::factory()->create(['bank_guarantee_status_id' => $wrongType->id]);

        $this->assertNull($custom->bankGuaranteeStatus()->first());
    }

    public function test_commitment_status_relation_is_scoped_to_its_own_status_type(): void
    {
        $wrongType = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS]);
        $custom = Custom::factory()->create(['commitment_status_id' => $wrongType->id]);

        $this->assertNull($custom->commitmentStatus()->first());
    }

    // Casts

    public function test_commitment_balance_is_cast_to_a_five_decimal_string(): void
    {
        $custom = Custom::factory()->create(['commitment_balance' => 1234.5]);

        $this->assertSame('1234.50000', $custom->commitment_balance);
    }

    public function test_date_columns_are_cast_to_carbon_instances(): void
    {
        $custom = Custom::factory()->create([
            'clearance_date' => '2026-05-10',
            'doc_submission_date' => '2026-05-01',
            'ten_percent_exit_date' => '2026-05-15',
            'rial_return_date' => '2026-06-01',
        ]);

        foreach (['clearance_date', 'doc_submission_date', 'ten_percent_exit_date', 'rial_return_date'] as $column) {
            $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $custom->{$column});
        }
    }

    // HasComputedAttributes — clearance_aging_days

    public function test_clearance_aging_days_computes_the_absolute_difference_between_doc_submission_and_clearance_dates(): void
    {
        $custom = Custom::factory()->create([
            'doc_submission_date' => '2026-05-01',
            'clearance_date' => '2026-05-10',
        ]);

        $this->assertSame(9, $custom->clearance_aging_days);
    }

    public function test_clearance_aging_days_falls_back_to_now_when_clearance_date_is_missing(): void
    {
        $custom = Custom::factory()->create([
            'doc_submission_date' => now()->subDays(5)->toDateString(),
            'clearance_date' => null,
        ]);

        $this->assertSame(5, $custom->clearance_aging_days);
    }

    public function test_clearance_aging_days_is_null_when_doc_submission_date_is_missing(): void
    {
        $custom = Custom::factory()->create([
            'doc_submission_date' => null,
            'clearance_date' => '2026-05-10',
        ]);

        $this->assertNull($custom->clearance_aging_days);
    }

    public function test_clearance_aging_days_is_appended_to_the_array_representation(): void
    {
        $custom = Custom::factory()->create([
            'doc_submission_date' => '2026-05-01',
            'clearance_date' => '2026-05-10',
        ]);

        $this->assertArrayHasKey('clearance_aging_days', $custom->toArray());
    }
}
