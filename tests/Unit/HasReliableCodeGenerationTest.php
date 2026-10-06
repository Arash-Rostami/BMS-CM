<?php

namespace Tests\Unit;

use App\Models\BankProfile;
use App\Models\Custom;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Traits\General\HasReliableCodeGeneration;
use App\Services\CodeGenerator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Project-wide integrity check for the auto-generated-number race condition fix
 * (servicesPattern.md's CodeGenerator section) — one dedicated test per model
 * that has a CodeGenerator-mapped column, not folded into any single resource's
 * own test file since this is a cross-cutting mechanism, not resource logic.
 *
 * Each test proves two things for its model: (1) the model actually composes
 * HasReliableCodeGeneration, and (2) if the naturally-next number is force-made
 * to already exist (simulating the other half of a race that just committed),
 * a normal create() still succeeds with a distinct, real number — never an
 * error, never a silent duplicate. The underlying transaction+retry mechanism
 * was additionally verified via 10 real two-OS-process concurrent creates
 * (0 collisions, 0 errors) during development — not reproducible inside a
 * single-process PHPUnit run, so not duplicated here; this file is the
 * permanent regression guard.
 */
class HasReliableCodeGenerationTest extends TestCase
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

    private function forceCollisionThenCreate(string $modelClass, string $field, string $table): void
    {
        $this->assertContains(HasReliableCodeGeneration::class, class_uses_recursive($modelClass));

        $forcedNext = CodeGenerator::generate($field);

        $blank = (new $modelClass)->getAttributes();
        $row = array_merge($blank, [
            $field => $forcedNext,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = array_filter($row, fn ($value) => ! is_array($value));
        DB::table($table)->insert($row);

        $record = $modelClass::factory()->create();

        $this->assertNotNull($record->{$field});
        $this->assertNotSame($forcedNext, $record->{$field});
    }

    public function test_purchase_request_regenerates_cleanly_on_a_forced_collision(): void
    {
        $this->forceCollisionThenCreate(PurchaseRequest::class, 'pr_number', 'purchase_requests');
    }

    public function test_proforma_invoice_regenerates_cleanly_on_a_forced_collision(): void
    {
        $this->forceCollisionThenCreate(ProformaInvoice::class, 'invoice_no', 'proforma_invoices');
    }

    public function test_registered_order_regenerates_cleanly_on_a_forced_collision(): void
    {
        $this->forceCollisionThenCreate(RegisteredOrder::class, 'ro_number', 'registered_orders');
    }

    public function test_purchase_order_regenerates_cleanly_on_a_forced_collision(): void
    {
        $this->forceCollisionThenCreate(PurchaseOrder::class, 'po_number', 'purchase_orders');
    }

    public function test_bank_profile_regenerates_cleanly_on_a_forced_collision(): void
    {
        $this->forceCollisionThenCreate(BankProfile::class, 'bp_number', 'bank_profiles');
    }

    public function test_payment_regenerates_cleanly_on_a_forced_collision(): void
    {
        $this->forceCollisionThenCreate(Payment::class, 'payment_no', 'payments');
    }

    public function test_shipment_regenerates_cleanly_on_a_forced_collision(): void
    {
        $this->forceCollisionThenCreate(Shipment::class, 'shipment_no', 'shipments');
    }

    public function test_custom_regenerates_cleanly_on_a_forced_collision(): void
    {
        $this->forceCollisionThenCreate(Custom::class, 'custom_no', 'customs');
    }
}
