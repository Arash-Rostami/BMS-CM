<?php

namespace Tests\Feature\Traits;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use App\Models\Traits\General\SellerEntity;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App\Models\Traits\General\SellerEntity provides three BelongsTo(Company, 'seller_id')
 * variants, each scoped by company type + is_active=1: manufacturerCompanyExclusive(),
 * sellerCompanyExclusive(), supplierCompanyExclusive(). Composed on 2 unrelated models
 * (RegisteredOrder, PurchaseOrder) — cross-cutting, no existing coverage.
 */
class SellerEntityTest extends TestCase
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

    public function test_purchase_order_composes_the_trait(): void
    {
        $this->assertContains(SellerEntity::class, class_uses_recursive(PurchaseOrder::class));
    }

    public function test_registered_order_composes_the_trait(): void
    {
        $this->assertContains(SellerEntity::class, class_uses_recursive(RegisteredOrder::class));
    }

    public function test_seller_company_exclusive_resolves_only_an_active_seller(): void
    {
        $seller = Company::factory()->seller()->create();
        $order = PurchaseOrder::factory()->create(['seller_id' => $seller->id]);

        $this->assertTrue($order->sellerCompanyExclusive()->first()->is($seller));
    }

    public function test_seller_company_exclusive_is_null_when_the_seller_id_points_to_an_inactive_company(): void
    {
        $seller = Company::factory()->seller()->create(['is_active' => false]);
        $order = PurchaseOrder::factory()->create(['seller_id' => $seller->id]);

        $this->assertNull($order->sellerCompanyExclusive()->first());
    }

    public function test_seller_company_exclusive_is_null_when_the_seller_id_points_to_a_company_of_the_wrong_type(): void
    {
        $buyerOnly = Company::factory()->buyer()->create();
        $order = PurchaseOrder::factory()->create(['seller_id' => $buyerOnly->id]);

        $this->assertNull($order->sellerCompanyExclusive()->first());
    }

    public function test_manufacturer_company_exclusive_resolves_only_an_active_manufacturer(): void
    {
        $manufacturer = Company::factory()->create(['types' => [Company::TYPE_MANUFACTURER], 'is_active' => true]);
        $order = RegisteredOrder::factory()->create(['seller_id' => $manufacturer->id]);

        $this->assertTrue($order->manufacturerCompanyExclusive()->first()->is($manufacturer));
    }

    public function test_supplier_company_exclusive_resolves_only_an_active_supplier(): void
    {
        $supplier = Company::factory()->create(['types' => [Company::TYPE_SUPPLIER], 'is_active' => true]);
        $order = RegisteredOrder::factory()->create(['seller_id' => $supplier->id]);

        $this->assertTrue($order->supplierCompanyExclusive()->first()->is($supplier));
    }
}
