<?php

namespace Tests\Feature\Traits;

use App\Models\Bank;
use App\Models\Company;
use App\Models\PurchaseRequest;
use App\Models\Traits\General\Relationships;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App\Models\Traits\General\Relationships is the audit creator()/updater() pair,
 * composed (unaliased) on 17+ unrelated models (Bank, Company, Currency, Department,
 * Correspondence, PurchaseRequest, ProformaInvoice, RegisteredOrder, PurchaseOrder,
 * Payment, Shipment, Custom, BankProfile, Product, EntityAttribute, Status, Target,
 * NotificationSetting, Specification, Attachment, …) — a genuine cross-cutting
 * mechanism per testPattern.md, so it gets its own file rather than folding into
 * any one model's test. Verified against a representative, unrelated sample
 * (Operational + Master) rather than every composing model, since the trait body
 * itself has no per-model branching — same `belongsTo` definitions everywhere.
 */
class RelationshipsTest extends TestCase
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

    public function test_bank_resolves_creator_and_updater_via_the_general_trait(): void
    {
        $this->assertContains(Relationships::class, class_uses_recursive(Bank::class));

        $creator = User::factory()->create();
        $updater = User::factory()->create();
        $bank = Bank::factory()->create(['user_id' => $creator->id, 'updated_by_id' => $updater->id]);

        $this->assertTrue($bank->creator()->is($creator));
        $this->assertTrue($bank->updater()->is($updater));
    }

    public function test_company_resolves_creator_and_updater_via_the_general_trait(): void
    {
        $this->assertContains(Relationships::class, class_uses_recursive(Company::class));

        $creator = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $creator->id, 'updated_by_id' => null]);

        $this->assertTrue($company->creator()->is($creator));
        $this->assertNull($company->updater);
    }

    public function test_purchase_request_resolves_creator_and_updater_via_the_general_trait_alongside_its_own_domain_relationships(): void
    {
        $this->assertContains(Relationships::class, class_uses_recursive(PurchaseRequest::class));

        $creator = User::factory()->create();
        $request = PurchaseRequest::factory()->create(['user_id' => $creator->id]);

        $this->assertTrue($request->creator()->is($creator));
        $this->assertSame('user_id', $request->creator()->getForeignKeyName());
        $this->assertSame('updated_by_id', $request->updater()->getForeignKeyName());
    }
}
