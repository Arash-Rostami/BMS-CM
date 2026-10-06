<?php

namespace Tests\Feature\Traits;

use App\Models\Bank;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Traits\General\HasScope;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App\Models\Traits\General\HasScope provides scopeActive() -> where('is_active', true).
 * Composed on 5 unrelated models (Bank, Currency, Company, Department, Product) —
 * cross-cutting, no existing coverage (only mentioned in a changelog note).
 */
class HasScopeTest extends TestCase
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

    public function test_bank_composes_the_trait(): void
    {
        $this->assertContains(HasScope::class, class_uses_recursive(Bank::class));
    }

    public function test_active_scope_excludes_inactive_bank_rows(): void
    {
        $active = Bank::factory()->create(['is_active' => true]);
        $inactive = Bank::factory()->inactive()->create();

        $ids = Bank::active()->pluck('id');

        $this->assertTrue($ids->contains($active->id));
        $this->assertFalse($ids->contains($inactive->id));
    }

    public function test_active_scope_works_identically_on_an_unrelated_model(): void
    {
        $active = Currency::factory()->create(['is_active' => true]);
        $inactive = Currency::factory()->inactive()->create();

        $ids = Currency::active()->pluck('id');

        $this->assertTrue($ids->contains($active->id));
        $this->assertFalse($ids->contains($inactive->id));
    }

    public function test_active_scope_composes_with_other_query_constraints(): void
    {
        $company = Company::factory()->seller()->create();
        $inactiveSeller = Company::factory()->seller()->create(['is_active' => false]);

        $ids = Company::active()->sellers()->pluck('id');

        $this->assertTrue($ids->contains($company->id));
        $this->assertFalse($ids->contains($inactiveSeller->id));
    }
}
