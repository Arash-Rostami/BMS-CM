<?php

namespace Tests\Feature\Models;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProformaInvoice;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProformaInvoiceModelTest extends TestCase
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

    // Scoped relations — sellerCompany / buyerCompany / mainCurrency / secondaryCurrency

    public function test_seller_company_relation_excludes_an_inactive_company(): void
    {
        $seller = Company::factory()->create(['is_active' => false]);
        $pi = ProformaInvoice::factory()->create(['seller_id' => $seller->id]);

        $this->assertNull($pi->fresh()->sellerCompany);
    }

    public function test_seller_company_relation_resolves_an_active_company(): void
    {
        $seller = Company::factory()->seller()->create();
        $pi = ProformaInvoice::factory()->create(['seller_id' => $seller->id]);

        $this->assertSame($seller->id, $pi->fresh()->sellerCompany?->id);
    }

    public function test_buyer_company_relation_excludes_an_inactive_company(): void
    {
        $buyer = Company::factory()->create(['is_active' => false]);
        $pi = ProformaInvoice::factory()->create(['buyer_id' => $buyer->id]);

        $this->assertNull($pi->fresh()->buyerCompany);
    }

    public function test_main_currency_relation_excludes_an_inactive_currency(): void
    {
        $currency = Currency::factory()->create(['is_active' => false]);
        $pi = ProformaInvoice::factory()->create(['main_currency_id' => $currency->id]);

        $this->assertNull($pi->fresh()->mainCurrency);
    }

    public function test_secondary_currency_relation_excludes_an_inactive_currency(): void
    {
        $currency = Currency::factory()->create(['is_active' => false]);
        $pi = ProformaInvoice::factory()->create(['secondary_currency_id' => $currency->id]);

        $this->assertNull($pi->fresh()->secondaryCurrency);
    }

    // formatted_name

    public function test_formatted_name_without_date_shows_invoice_number_and_both_companies(): void
    {
        app()->setLocale('en');
        $seller = Company::factory()->seller()->create(['english_name' => 'Acme Seller']);
        $buyer = Company::factory()->buyer()->create(['english_name' => 'Acme Buyer']);
        $pi = ProformaInvoice::factory()->create(['seller_id' => $seller->id, 'buyer_id' => $buyer->id]);
        $pi->load('sellerCompany', 'buyerCompany');

        $formatted = $pi->formatted_name_without_date;

        $this->assertStringContainsString($pi->invoice_no, $formatted);
        $this->assertStringContainsString('Acme Seller', $formatted);
        $this->assertStringContainsString('Acme Buyer', $formatted);
    }

    public function test_formatted_name_without_date_falls_back_when_seller_is_missing(): void
    {
        app()->setLocale('en');
        $pi = ProformaInvoice::factory()->create(['seller_id' => null]);
        $pi->load('sellerCompany', 'buyerCompany');

        $this->assertStringContainsString('Unknown Seller', $pi->formatted_name_without_date);
    }

    public function test_formatted_name_with_date_includes_invoice_and_validity_dates(): void
    {
        app()->setLocale('en');
        $pi = ProformaInvoice::factory()->create([
            'invoice_date' => '2026-01-10',
            'validity_date' => '2026-04-10',
        ]);
        $pi->load('sellerCompany', 'buyerCompany');

        $formatted = $pi->formatted_name;

        $this->assertStringContainsString('Invoice Date', $formatted);
        $this->assertStringContainsString('Valid Until', $formatted);
    }

    // invoice_no is force-regenerated on create by CodeGeneratingObserver — same as PurchaseRequest.pr_number

    public function test_invoice_no_is_generated_on_create_with_the_pi_prefix(): void
    {
        $pi = ProformaInvoice::factory()->create();

        $this->assertStringStartsWith('PI-', $pi->fresh()->invoice_no);
    }

    // scopeSearchAll

    public function test_search_all_scope_matches_invoice_no(): void
    {
        $target = ProformaInvoice::factory()->create();
        $term = substr($target->invoice_no, -4);

        $results = ProformaInvoice::searchAll($term)->get();

        $this->assertTrue($results->contains('id', $target->id));
    }

    public function test_search_all_scope_matches_contract_no(): void
    {
        $target = ProformaInvoice::factory()->create(['contract_no' => 'CT-778899']);

        $results = ProformaInvoice::searchAll('778899')->get();

        $this->assertTrue($results->contains('id', $target->id));
    }

    public function test_search_all_scope_with_blank_term_returns_query_unmodified(): void
    {
        $before = ProformaInvoice::count();
        ProformaInvoice::factory()->count(3)->create();

        $this->assertSame($before + 3, ProformaInvoice::searchAll('   ')->count());
    }

    // Casts + soft delete

    public function test_total_amount_is_cast_to_decimal_string(): void
    {
        $pi = ProformaInvoice::factory()->create(['total_amount' => 4321.5]);

        $this->assertSame('4321.50000', $pi->fresh()->total_amount);
    }

    public function test_soft_delete_removes_from_default_query_but_keeps_row(): void
    {
        $pi = ProformaInvoice::factory()->create();
        $pi->delete();

        $this->assertNull(ProformaInvoice::find($pi->id));
        $this->assertTrue(ProformaInvoice::withTrashed()->find($pi->id)->trashed());
    }

    // Repeater lines — ProformaInvoiceItem

    public function test_repeater_items_belong_to_the_invoice_and_product_and_cast_amounts_to_five_decimals(): void
    {
        $pi = ProformaInvoice::factory()->create();
        $product = Product::factory()->create();

        $pi->items()->create(['product_id' => $product->id, 'quantity' => 12.5, 'unit' => 'kg', 'unit_price' => 3.25, 'freight_charges' => 1, 'total_amount' => 40.625]);

        $item = $pi->fresh()->items->first();

        $this->assertTrue($item->proformaInvoice->is($pi));
        $this->assertTrue($item->product->is($product));
        $this->assertSame(['12.50000', '3.25000', '1.00000', '40.62500'], [$item->quantity, $item->unit_price, $item->freight_charges, $item->total_amount]);
    }

    public function test_a_removed_repeater_item_is_soft_deleted_and_leaves_the_invoice_lines(): void
    {
        $pi = ProformaInvoice::factory()->create();
        $product = Product::factory()->create();
        $keep = $pi->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 1, 'total_amount' => 1]);
        $drop = $pi->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit' => 'pcs', 'unit_price' => 1, 'total_amount' => 2]);

        $drop->delete();

        $this->assertSame([$keep->id], $pi->fresh()->items->pluck('id')->all());
        $this->assertTrue($pi->items()->withTrashed()->whereKey($drop->id)->first()->trashed());
    }
}
