<?php

namespace Tests\Feature\Models;

use App\Models\Currency;
use App\Models\ProformaInvoice;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurrencyModelTest extends TestCase
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

    public function test_proforma_invoices_as_main_returns_only_invoices_on_that_currency(): void
    {
        $uniq = uniqid();
        $main = Currency::factory()->create(['english_name' => "USD-{$uniq}"]);
        $other = Currency::factory()->create(['english_name' => "EUR-{$uniq}"]);

        $asMain = ProformaInvoice::factory()->create(['main_currency_id' => $main->id]);
        $onOther = ProformaInvoice::factory()->create(['main_currency_id' => $other->id]);

        $ids = $main->proformaInvoicesAsMain()->pluck('id');

        $this->assertTrue($ids->contains($asMain->id));
        $this->assertFalse($ids->contains($onOther->id));
    }

    public function test_proforma_invoices_as_secondary_returns_only_invoices_on_that_currency(): void
    {
        $uniq = uniqid();
        $secondary = Currency::factory()->create(['english_name' => "GBP-{$uniq}"]);
        $main = Currency::factory()->create(['english_name' => "USD2-{$uniq}"]);

        $asSecondary = ProformaInvoice::factory()->create([
            'main_currency_id' => $main->id,
            'secondary_currency_id' => $secondary->id,
        ]);
        $asMain = ProformaInvoice::factory()->create([
            'main_currency_id' => $secondary->id,
            'secondary_currency_id' => null,
        ]);

        $ids = $secondary->proformaInvoicesAsSecondary()->pluck('id');

        $this->assertTrue($ids->contains($asSecondary->id));
        $this->assertFalse($ids->contains($asMain->id));
    }

    public function test_is_active_is_cast_to_a_boolean_and_soft_delete_hides_the_row(): void
    {
        $currency = Currency::factory()->create();
        $inactive = Currency::factory()->inactive()->create();

        $this->assertTrue($currency->is_active);
        $this->assertIsBool($inactive->is_active);
        $this->assertFalse($inactive->is_active);

        $currency->delete();

        $this->assertNull(Currency::find($currency->id));
        $this->assertNotNull(Currency::withTrashed()->find($currency->id));
    }
}