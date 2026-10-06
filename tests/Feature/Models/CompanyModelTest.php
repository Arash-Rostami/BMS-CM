<?php

namespace Tests\Feature\Models;

use App\Models\BankProfile;
use App\Models\Company;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class CompanyModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        app()->setLocale('en');
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

    public function test_type_constants_pin_the_type_vocabulary(): void
    {
        $this->assertSame('seller', Company::TYPE_SELLER);
        $this->assertSame('buyer', Company::TYPE_BUYER);
        $this->assertSame('service_provider', Company::TYPE_SERVICE_PROVIDER);
        $this->assertSame(['supplier', 'manufacturer', 'seller'], Company::TYPE_SERVICE_ALL_SELLERS);
    }

    public function test_get_available_types_localizes_by_locale(): void
    {
        $english = Company::getAvailableTypes();
        $this->assertSame('Seller', $english[Company::TYPE_SELLER]);
        $this->assertSame('Service Provider', $english[Company::TYPE_SERVICE_PROVIDER]);

        app()->setLocale('fa');
        $persian = Company::getAvailableTypes();
        $this->assertSame('فروشنده', $persian[Company::TYPE_SELLER]);

        app()->setLocale('en');
    }

    public function test_formatted_types_maps_labels_and_falls_back_for_unknown_types(): void
    {
        $mapped = Company::factory()->make(['types' => [Company::TYPE_SELLER, 'weird_kind']]);
        $this->assertSame(['Seller', 'Weird kind'], $mapped->formatted_types);

        $this->assertSame([], Company::factory()->make(['types' => null])->formatted_types);
    }

    public function test_of_type_filters_by_a_single_type(): void
    {
        $uniq = uniqid();
        $seller = Company::factory()->create(['name' => "Sell {$uniq}", 'english_name' => "Sell {$uniq}", 'types' => [Company::TYPE_SELLER]]);
        $buyer = Company::factory()->create(['name' => "Buy {$uniq}", 'english_name' => "Buy {$uniq}", 'types' => [Company::TYPE_BUYER]]);

        $ids = Company::ofType(Company::TYPE_SELLER)->pluck('id');

        $this->assertTrue($ids->contains($seller->id));
        $this->assertFalse($ids->contains($buyer->id));
    }

    public function test_of_any_type_matches_any_while_of_all_types_requires_all(): void
    {
        $uniq = uniqid();
        $sellerOnly = Company::factory()->create(['english_name' => "A {$uniq}", 'types' => [Company::TYPE_SELLER]]);
        $both = Company::factory()->create(['english_name' => "B {$uniq}", 'types' => [Company::TYPE_SELLER, Company::TYPE_BUYER]]);

        $anyIds = Company::ofAnyType([Company::TYPE_SELLER, Company::TYPE_BUYER])
            ->whereIn('id', [$sellerOnly->id, $both->id])->pluck('id');
        $this->assertTrue($anyIds->contains($sellerOnly->id));
        $this->assertTrue($anyIds->contains($both->id));

        $allIds = Company::ofAllTypes([Company::TYPE_SELLER, Company::TYPE_BUYER])
            ->whereIn('id', [$sellerOnly->id, $both->id])->pluck('id');
        $this->assertFalse($allIds->contains($sellerOnly->id));
        $this->assertTrue($allIds->contains($both->id));
    }

    public function test_named_scopes_route_to_their_type_groups(): void
    {
        $uniq = uniqid();
        $seller = Company::factory()->create(['english_name' => "S {$uniq}", 'types' => [Company::TYPE_SELLER]]);
        $buyer = Company::factory()->create(['english_name' => "B {$uniq}", 'types' => [Company::TYPE_BUYER]]);
        $supplier = Company::factory()->create(['english_name' => "U {$uniq}", 'types' => [Company::TYPE_SUPPLIER]]);
        $retailer = Company::factory()->create(['english_name' => "R {$uniq}", 'types' => [Company::TYPE_RETAILER]]);

        $this->assertTrue(Company::sellers()->pluck('id')->contains($seller->id));
        $this->assertFalse(Company::sellers()->pluck('id')->contains($buyer->id));

        $trading = Company::tradingCompanies()->whereIn('id', [$seller->id, $buyer->id, $retailer->id])->pluck('id');
        $this->assertTrue($trading->contains($seller->id));
        $this->assertTrue($trading->contains($buyer->id));
        $this->assertFalse($trading->contains($retailer->id));

        $chain = Company::supplyChainCompanies()->whereIn('id', [$supplier->id, $buyer->id, $retailer->id])->pluck('id');
        $this->assertTrue($chain->contains($supplier->id));
        $this->assertFalse($chain->contains($buyer->id));
        $this->assertFalse($chain->contains($retailer->id));
    }

    public function test_search_company_matches_either_name_and_ignores_blank_terms(): void
    {
        $uniq = 'Cmp'.uniqid();
        $byName = Company::factory()->create(['name' => "{$uniq} Persian", 'english_name' => 'Other']);
        $byEnglish = Company::factory()->create(['name' => 'Other', 'english_name' => "{$uniq} English"]);
        $unrelated = Company::factory()->create();

        $ids = Company::searchCompany($uniq)->pluck('id');

        $this->assertTrue($ids->contains($byName->id));
        $this->assertTrue($ids->contains($byEnglish->id));
        $this->assertFalse($ids->contains($unrelated->id));

        $baseline = Company::count();
        $this->assertSame($baseline, Company::searchCompany('')->count());
    }

    public function test_seller_company_sort_orders_the_parent_table_by_company_name(): void
    {
        $uniq = uniqid();
        $alpha = Company::factory()->create(['english_name' => "Alpha {$uniq}", 'types' => [Company::TYPE_SELLER], 'is_active' => true]);
        $beta = Company::factory()->create(['english_name' => "Beta {$uniq}", 'types' => [Company::TYPE_SUPPLIER], 'is_active' => true]);

        $roBeta = RegisteredOrder::factory()->create(['seller_id' => $beta->id]);
        $roAlpha = RegisteredOrder::factory()->create(['seller_id' => $alpha->id]);

        $reflection = new ReflectionMethod(Company::class, 'getSellerCompanySort');
        $reflection->setAccessible(true);
        $sort = $reflection->invoke(null, 'registered_orders.seller_id');

        $this->assertIsCallable($sort);

        $sorted = $sort(RegisteredOrder::query()->whereKey([$roAlpha->id, $roBeta->id]), 'asc');

        $this->assertSame([$roAlpha->id, $roBeta->id], $sorted->pluck('id')->all());
    }

    public function test_is_active_is_cast_to_a_boolean_and_soft_delete_hides_the_row(): void
    {
        $company = Company::factory()->create();

        $this->assertIsBool($company->is_active);

        $company->delete();

        $this->assertNull(Company::find($company->id));
        $this->assertNotNull(Company::withTrashed()->find($company->id));
    }

    public function test_usage_guard_relations_resolve_the_correct_inverse_rows(): void
    {
        $company = Company::factory()->create();

        $proformaInvoiceAsSeller = ProformaInvoice::factory()->create(['seller_id' => $company->id]);
        $proformaInvoiceAsBuyer = ProformaInvoice::factory()->create(['buyer_id' => $company->id]);
        $purchaseOrderAsSeller = PurchaseOrder::factory()->create(['seller_id' => $company->id]);
        $purchaseOrderAsBuyer = PurchaseOrder::factory()->create(['buyer_id' => $company->id]);
        $registeredOrderAsSeller = RegisteredOrder::factory()->create(['seller_id' => $company->id]);
        $registeredOrderAsBuyer = RegisteredOrder::factory()->create(['buyer_id' => $company->id]);
        $paymentAsPayor = Payment::factory()->create(['payor_id' => $company->id]);
        $paymentAsPayee = Payment::factory()->create(['payee_id' => $company->id]);
        $bankProfile = BankProfile::factory()->create(['company_id' => $company->id]);
        $shipment = Shipment::factory()->create(['company_id' => $company->id]);

        $this->assertTrue($company->proformaInvoicesAsSeller->pluck('id')->contains($proformaInvoiceAsSeller->id));
        $this->assertTrue($company->proformaInvoicesAsBuyer->pluck('id')->contains($proformaInvoiceAsBuyer->id));
        $this->assertTrue($company->purchaseOrdersAsSeller->pluck('id')->contains($purchaseOrderAsSeller->id));
        $this->assertTrue($company->purchaseOrdersAsBuyer->pluck('id')->contains($purchaseOrderAsBuyer->id));
        $this->assertTrue($company->registeredOrdersAsSeller->pluck('id')->contains($registeredOrderAsSeller->id));
        $this->assertTrue($company->registeredOrdersAsBuyer->pluck('id')->contains($registeredOrderAsBuyer->id));
        $this->assertTrue($company->paymentsAsPayor->pluck('id')->contains($paymentAsPayor->id));
        $this->assertTrue($company->paymentsAsPayee->pluck('id')->contains($paymentAsPayee->id));
        $this->assertTrue($company->bankProfiles->pluck('id')->contains($bankProfile->id));
        $this->assertTrue($company->shipments->pluck('id')->contains($shipment->id));
    }
}