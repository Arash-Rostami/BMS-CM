<?php

namespace Tests\Feature\Models;

use App\Models\Company;
use App\Models\RegisteredOrder;
use App\Models\RegisteredOrderItem;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegisteredOrderModelTest extends TestCase
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

    // Scoped relations — sellerCompanyExclusive / buyerCompany / status

    public function test_buyer_company_relation_excludes_an_inactive_company(): void
    {
        $buyer = Company::factory()->create(['is_active' => false]);
        $ro = RegisteredOrder::factory()->create(['buyer_id' => $buyer->id]);

        $this->assertNull($ro->fresh()->buyerCompany);
    }

    public function test_seller_company_exclusive_relation_resolves_an_active_seller(): void
    {
        $seller = Company::factory()->seller()->create();
        $ro = RegisteredOrder::factory()->create(['seller_id' => $seller->id]);

        $this->assertSame($seller->id, $ro->fresh()->sellerCompanyExclusive?->id);
    }

    public function test_status_relation_is_scoped_to_the_registered_order_status_type(): void
    {
        $wrongType = Status::factory()->create(['english_type' => 'Purchase Order Status']);
        $ro = RegisteredOrder::factory()->create(['status_id' => $wrongType->id]);

        $this->assertNull($ro->fresh()->status);
    }

    // total_amount / total_quantity — computed from items, not a DB column

    public function test_total_amount_sums_line_total_not_just_quantity_times_unit_price(): void
    {
        // line_total (quantity*unit_price + shipping_cost + extra_cost) is this project's established
        // "true total per RO item" contract — see AnalyticsService::239, RegisteredOrderExporter, and
        // the form's own live preview (ItemCalculation::updateItemLineTotal). A naive quantity*unit_price
        // sum would read 40 here, not 45 — shipping/extra costs must be included.
        $ro = RegisteredOrder::factory()->create();
        RegisteredOrderItem::factory()->create([
            'registered_order_id' => $ro->id,
            'quantity' => 3,
            'unit_price' => 10,
            'shipping_cost' => 4,
            'extra_cost' => 1,
            'line_total' => 35,
        ]);
        RegisteredOrderItem::factory()->create([
            'registered_order_id' => $ro->id,
            'quantity' => 2,
            'unit_price' => 5,
            'shipping_cost' => 0,
            'extra_cost' => 0,
            'line_total' => 10,
        ]);

        $fresh = $ro->fresh();

        $this->assertEquals(45, $fresh->total_amount);
        $this->assertEquals(5, $fresh->total_quantity);
    }

    public function test_total_amount_and_total_quantity_are_zero_without_items(): void
    {
        $ro = RegisteredOrder::factory()->create();

        $this->assertEquals(0, $ro->fresh()->total_amount);
        $this->assertEquals(0, $ro->fresh()->total_quantity);
    }

    // formatted_name

    public function test_formatted_name_without_date_shows_ro_number_and_both_companies(): void
    {
        app()->setLocale('en');
        $seller = Company::factory()->seller()->create(['english_name' => 'Acme Seller']);
        $buyer = Company::factory()->buyer()->create(['english_name' => 'Acme Buyer']);
        $ro = RegisteredOrder::factory()->create(['seller_id' => $seller->id, 'buyer_id' => $buyer->id]);
        $ro->load('sellerCompany', 'buyerCompany');

        $formatted = $ro->formatted_name_without_date;

        $this->assertStringContainsString($ro->ro_number, $formatted);
        $this->assertStringContainsString('Acme Seller', $formatted);
        $this->assertStringContainsString('Acme Buyer', $formatted);
    }

    public function test_formatted_name_without_date_falls_back_when_seller_is_inactive(): void
    {
        app()->setLocale('en');
        $seller = Company::factory()->seller()->create(['is_active' => false]);
        $ro = RegisteredOrder::factory()->create(['seller_id' => $seller->id]);
        $ro->load('sellerCompany', 'buyerCompany');

        $this->assertStringContainsString('Unknown Seller', $ro->formatted_name_without_date);
    }

    public function test_formatted_name_with_date_includes_order_and_validity_dates(): void
    {
        app()->setLocale('en');
        $ro = RegisteredOrder::factory()->create([
            'order_date' => '2026-01-10',
            'validity_date' => '2026-04-10',
        ]);
        $ro->load('sellerCompany', 'buyerCompany');

        $formatted = $ro->formatted_name;

        $this->assertStringContainsString('Order Date', $formatted);
        $this->assertStringContainsString('Valid Until', $formatted);
    }

    // scopeSearchAll

    public function test_search_all_scope_matches_ro_number(): void
    {
        $target = RegisteredOrder::factory()->create();
        $term = substr($target->ro_number, -4);

        $results = RegisteredOrder::searchAll($term)->get();

        $this->assertTrue($results->contains('id', $target->id));
    }

    public function test_search_all_scope_matches_contract_no(): void
    {
        // contract_no is regenerated on create by CodeGeneratingObserver — same gotcha as
        // PurchaseRequest.pr_number (testPattern.md §3c); assign it directly afterwards.
        $target = RegisteredOrder::factory()->create();
        RegisteredOrder::whereKey($target->id)->update(['contract_no' => 'CT-778899']);
        $target->refresh();

        $results = RegisteredOrder::searchAll('778899')->get();

        $this->assertTrue($results->contains('id', $target->id));
    }

    public function test_search_all_scope_with_blank_term_returns_query_unmodified(): void
    {
        $before = RegisteredOrder::count();
        RegisteredOrder::factory()->count(3)->create();

        $this->assertSame($before + 3, RegisteredOrder::searchAll('   ')->count());
    }

    // Soft delete

    public function test_soft_delete_removes_from_default_query_but_keeps_row(): void
    {
        $ro = RegisteredOrder::factory()->create();
        $ro->delete();

        $this->assertNull(RegisteredOrder::find($ro->id));
        $this->assertTrue(RegisteredOrder::withTrashed()->find($ro->id)->trashed());
    }

    // TracksStatusHistory

    public function test_updating_status_id_writes_a_status_history_row_with_from_to_and_actor(): void
    {
        $from = Status::factory()->create();
        $to = Status::factory()->create();
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $ro = RegisteredOrder::factory()->create(['status_id' => $from->id]);
        $ro->update(['status_id' => $to->id]);

        $history = $ro->statusHistories()->latest('id')->first();

        $this->assertSame('status_id', $history->field);
        $this->assertSame($from->id, $history->from_status_id);
        $this->assertSame($to->id, $history->to_status_id);
        $this->assertSame($actor->id, $history->user_id);
    }
}
