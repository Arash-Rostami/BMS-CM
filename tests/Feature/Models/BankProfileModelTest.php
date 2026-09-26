<?php

namespace Tests\Feature\Models;

use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Status;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BankProfileModelTest extends TestCase
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

    // Scoped relations — bank / company / requestedCurrency / purchasedCurrency / status

    public function test_bank_relation_excludes_an_inactive_bank(): void
    {
        $bank = Bank::factory()->create(['is_active' => false]);
        $bp = BankProfile::factory()->create(['bank_id' => $bank->id]);

        $this->assertNull($bp->fresh()->bank);
    }

    public function test_company_relation_excludes_an_inactive_company(): void
    {
        $company = Company::factory()->create(['is_active' => false]);
        $bp = BankProfile::factory()->create(['company_id' => $company->id]);

        $this->assertNull($bp->fresh()->company);
    }

    public function test_requested_currency_relation_excludes_an_inactive_currency(): void
    {
        $currency = Currency::factory()->inactive()->create();
        $bp = BankProfile::factory()->create(['requested_currency_id' => $currency->id]);

        $this->assertNull($bp->fresh()->requestedCurrency);
    }

    public function test_purchased_currency_relation_excludes_an_inactive_currency(): void
    {
        $currency = Currency::factory()->inactive()->create();
        $bp = BankProfile::factory()->create(['purchased_currency_id' => $currency->id]);

        $this->assertNull($bp->fresh()->purchasedCurrency);
    }

    public function test_status_relation_is_scoped_to_the_bank_profile_status_type(): void
    {
        $wrongType = Status::factory()->create(['english_type' => 'Registered Order Status']);
        $bp = BankProfile::factory()->create(['status_id' => $wrongType->id]);

        $this->assertNull($bp->fresh()->status);
    }

    // scopeSearchAll

    public function test_search_all_scope_matches_bp_number(): void
    {
        $target = BankProfile::factory()->create();
        $term = substr($target->bp_number, -4);

        $results = BankProfile::searchAll($term)->get();

        $this->assertTrue($results->contains('id', $target->id));
    }

    public function test_search_all_scope_matches_order_number(): void
    {
        $target = BankProfile::factory()->create(['order_number' => 'ORD-778899']);

        $results = BankProfile::searchAll('778899')->get();

        $this->assertTrue($results->contains('id', $target->id));
    }

    public function test_search_all_scope_with_blank_term_returns_query_unmodified(): void
    {
        $before = BankProfile::count();
        BankProfile::factory()->count(3)->create();

        $this->assertSame($before + 3, BankProfile::searchAll('   ')->count());
    }

    // commission_amount_purchased — raw value wins, else computed from purchased_equivalent * commission_rate

    public function test_commission_amount_purchased_uses_the_raw_column_when_present(): void
    {
        $bp = new BankProfile(['commission_amount_purchased' => 75, 'purchased_equivalent' => 1000, 'commission_rate' => 5]);

        $this->assertEquals(75, $bp->commission_amount_purchased);
    }

    public function test_commission_amount_purchased_falls_back_to_rate_computation_when_raw_is_blank(): void
    {
        $bp = new BankProfile(['purchased_equivalent' => 1000, 'commission_rate' => 5]);

        $this->assertEquals(50, $bp->commission_amount_purchased);
    }

    // commission_input_mode

    public function test_commission_input_mode_is_amount_when_raw_commission_amount_is_present(): void
    {
        $bp = new BankProfile(['commission_amount_purchased' => 75]);

        $this->assertSame('amount', $bp->commission_input_mode);
    }

    public function test_commission_input_mode_is_rate_when_raw_commission_amount_is_blank(): void
    {
        $bp = new BankProfile;

        $this->assertSame('rate', $bp->commission_input_mode);
    }

    // commission_equivalent — (requested / purchased) * commissionAmount

    public function test_commission_equivalent_scales_the_commission_amount_by_the_requested_to_purchased_ratio(): void
    {
        $bp = new BankProfile(['purchased_equivalent' => 1000, 'requested_amount' => 2000, 'commission_amount_purchased' => 50]);

        $this->assertEquals(100, $bp->commission_equivalent);
    }

    public function test_commission_equivalent_is_zero_when_purchased_or_requested_is_zero(): void
    {
        $zeroPurchased = new BankProfile(['purchased_equivalent' => 0, 'requested_amount' => 2000, 'commission_amount_purchased' => 50]);
        $zeroRequested = new BankProfile(['purchased_equivalent' => 1000, 'requested_amount' => 0, 'commission_amount_purchased' => 50]);

        $this->assertEquals(0, $zeroPurchased->commission_equivalent);
        $this->assertEquals(0, $zeroRequested->commission_equivalent);
    }

    // final_equivalent — (purchased / requested) * final_rate

    public function test_final_equivalent_scales_the_final_rate_by_the_purchased_to_requested_ratio(): void
    {
        $bp = new BankProfile(['requested_amount' => 2000, 'purchased_equivalent' => 1000, 'final_rate' => 500]);

        $this->assertEquals(250, $bp->final_equivalent);
    }

    public function test_final_equivalent_is_zero_when_requested_is_zero(): void
    {
        $bp = new BankProfile(['requested_amount' => 0, 'purchased_equivalent' => 1000, 'final_rate' => 500]);

        $this->assertEquals(0, $bp->final_equivalent);
    }

    // final_rate_display — getter passes through final_rate; setter recomputes final_rate from exchange_rate/commission_rate

    public function test_final_rate_display_getter_passes_through_the_stored_final_rate(): void
    {
        $bp = new BankProfile(['final_rate' => 321.5]);

        $this->assertEquals(321.5, $bp->final_rate_display);
    }

    public function test_final_rate_display_setter_never_actually_updates_the_real_final_rate_column(): void
    {
        $bp = new BankProfile(['exchange_rate' => 400, 'commission_rate' => 25, 'final_rate' => 999]);

        $bp->final_rate_display = 'ignored-input';

        $this->assertEquals(999, $bp->final_rate);
        $this->assertEquals(999, $bp->final_rate_display);
    }

    // remaining_commitment — requested_amount - documents_amount

    public function test_remaining_commitment_subtracts_documents_amount_from_requested_amount(): void
    {
        $bp = new BankProfile(['requested_amount' => 1000, 'documents_amount' => 300]);

        $this->assertEquals(700, $bp->remaining_commitment);
    }

    // total_purchased_remittance — purchased_equivalent + commission_amount_purchased

    public function test_total_purchased_remittance_adds_the_commission_amount_to_the_purchased_equivalent(): void
    {
        $bp = new BankProfile(['purchased_equivalent' => 1000, 'commission_amount_purchased' => 50]);

        $this->assertEquals(1050, $bp->total_purchased_remittance);
    }

    // total_requested_remittance — requested_amount + commission_equivalent

    public function test_total_requested_remittance_adds_the_commission_equivalent_to_the_requested_amount(): void
    {
        $bp = new BankProfile(['requested_amount' => 2000, 'purchased_equivalent' => 1000, 'commission_amount_purchased' => 50]);

        $this->assertEquals(2100, $bp->total_requested_remittance);
    }

    // total_rial_remittance — purchased_currency_id 1 (Rial) uses purchased_equivalent, any other currency uses requested_amount

    public function test_total_rial_remittance_uses_purchased_equivalent_when_purchased_currency_is_rial(): void
    {
        $bp = new BankProfile(['purchased_currency_id' => 1, 'purchased_equivalent' => 800, 'requested_amount' => 999, 'exchange_rate' => 50]);

        $this->assertEquals(40000, $bp->total_rial_remittance);
    }

    public function test_total_rial_remittance_uses_requested_amount_when_purchased_currency_is_not_rial(): void
    {
        $bp = new BankProfile(['purchased_currency_id' => 2, 'purchased_equivalent' => 999, 'requested_amount' => 1000, 'exchange_rate' => 50]);

        $this->assertEquals(50000, $bp->total_rial_remittance);
    }

    // waiting_duration — days between creation_date and allocation_date (or now, when still pending)

    public function test_waiting_duration_is_null_without_a_creation_date(): void
    {
        $bp = new BankProfile;

        $this->assertNull($bp->waiting_duration);
    }

    public function test_waiting_duration_counts_days_between_creation_and_allocation_dates(): void
    {
        $bp = new BankProfile(['creation_date' => '2026-01-01', 'allocation_date' => '2026-01-10']);

        $this->assertSame(9, $bp->waiting_duration);
    }

    public function test_waiting_duration_counts_days_up_to_now_when_still_awaiting_allocation(): void
    {
        Carbon::setTestNow('2026-01-15');
        $bp = new BankProfile(['creation_date' => '2026-01-01']);

        $this->assertSame(14, $bp->waiting_duration);

        Carbon::setTestNow();
    }

    // Soft delete

    public function test_soft_delete_removes_from_default_query_but_keeps_row(): void
    {
        $bp = BankProfile::factory()->create();
        $bp->delete();

        $this->assertNull(BankProfile::find($bp->id));
        $this->assertTrue(BankProfile::withTrashed()->find($bp->id)->trashed());
    }

    // TracksStatusHistory

    public function test_updating_status_id_writes_a_status_history_row_with_from_to_and_actor(): void
    {
        $from = Status::factory()->create();
        $to = Status::factory()->create();
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $bp = BankProfile::factory()->create(['status_id' => $from->id]);
        $bp->update(['status_id' => $to->id]);

        $history = $bp->statusHistories()->latest('id')->first();

        $this->assertSame('status_id', $history->field);
        $this->assertSame($from->id, $history->from_status_id);
        $this->assertSame($to->id, $history->to_status_id);
        $this->assertSame($actor->id, $history->user_id);
    }
}
