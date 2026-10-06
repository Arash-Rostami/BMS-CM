<?php

namespace Tests\Feature\Traits;

use App\Models\Bank;
use App\Models\Company;
use App\Models\Status;
use App\Models\Traits\General\HasNameSearch;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App\Models\Traits\General\HasNameSearch provides scopeSearchByName() — an
 * OR-across-name/english_name LIKE scope with an empty-term no-op guard. Composed
 * on 3 unrelated models (Company, Bank, Status) — cross-cutting, no existing coverage.
 */
class HasNameSearchTest extends TestCase
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
        $this->assertContains(HasNameSearch::class, class_uses_recursive(Bank::class));
    }

    public function test_search_by_name_matches_against_either_name_or_english_name(): void
    {
        $uniq = 'Trxyz'.uniqid();
        $byName = Bank::factory()->create(['name' => "{$uniq} Persian", 'english_name' => 'Other English']);
        $byEnglish = Bank::factory()->create(['name' => 'Other Persian', 'english_name' => "{$uniq} English"]);
        $unrelated = Bank::factory()->create(['name' => 'Totally Different', 'english_name' => 'Totally Different']);

        $ids = Bank::searchByName($uniq)->pluck('id');

        $this->assertTrue($ids->contains($byName->id));
        $this->assertTrue($ids->contains($byEnglish->id));
        $this->assertFalse($ids->contains($unrelated->id));
    }

    public function test_search_by_name_is_a_no_op_for_a_blank_or_whitespace_term(): void
    {
        Company::factory()->count(2)->create();

        $baseline = Company::count();

        $this->assertSame($baseline, Company::searchByName('')->count());
        $this->assertSame($baseline, Company::searchByName('   ')->count());
    }

    public function test_search_by_name_generalizes_to_an_unrelated_model(): void
    {
        $uniq = 'StatusTerm'.uniqid();
        $match = Status::factory()->create(['name' => $uniq, 'english_name' => $uniq]);
        $other = Status::factory()->create();

        $ids = Status::searchByName($uniq)->pluck('id');

        $this->assertTrue($ids->contains($match->id));
        $this->assertFalse($ids->contains($other->id));
    }
}
