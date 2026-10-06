<?php

namespace Tests\Feature\Models;

use App\Models\Bank;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BankModelTest extends TestCase
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

    public function test_is_active_is_cast_to_a_boolean(): void
    {
        $active = Bank::factory()->create();
        $inactive = Bank::factory()->inactive()->create();

        $this->assertIsBool($active->is_active);
        $this->assertTrue($active->is_active);
        $this->assertFalse($inactive->is_active);
    }

    public function test_soft_delete_hides_the_row_and_restore_brings_it_back(): void
    {
        $bank = Bank::factory()->create();

        $bank->delete();

        $this->assertNull(Bank::find($bank->id));
        $this->assertNotNull(Bank::withTrashed()->find($bank->id));
        $this->assertTrue($bank->fresh()->trashed());

        $bank->fresh()->restore();

        $this->assertNotNull(Bank::find($bank->id));
        $this->assertFalse($bank->fresh()->trashed());
    }
}