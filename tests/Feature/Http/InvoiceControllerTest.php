<?php

namespace Tests\Feature\Http;

use App\Filament\Resources\ShipmentResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
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

    private function actingAsUserWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create();

        $role = Role::create(['name' => 'invoice_controller_test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        return $user;
    }

    public function test_guests_get_401(): void
    {
        $shipment = Shipment::factory()->create();

        $this->getJson(route('shipments.invoice.pdf', $shipment))->assertUnauthorized();
    }

    public function test_a_user_without_shipment_view_gets_403(): void
    {
        $shipment = Shipment::factory()->create();
        $this->actingAsUserWithPermissions([]);

        $this->get(route('shipments.invoice.pdf', $shipment))->assertForbidden();
    }

    public function test_a_shipment_without_a_saved_invoice_gets_404(): void
    {
        $shipment = Shipment::factory()->create();
        $this->actingAsUserWithPermissions(['shipment.view']);

        $this->get(route('shipments.invoice.pdf', $shipment))->assertNotFound();
    }

    public function test_a_saved_invoice_with_relational_items_downloads_as_a_pdf(): void
    {
        $shipment = Shipment::factory()->create();
        ShipmentResource::persistInvoice([
            '_inv_pi_id' => 7,
            '_inv_invoice_no' => 'INV-PDF',
            '_inv_items' => [
                ['english_name' => 'Resin', 'quantity' => 2, 'unit_price' => 50, 'total_amount' => 100, 'net_weight' => 4, 'gross_weight' => 5],
            ],
        ], $shipment);
        $this->actingAsUserWithPermissions(['shipment.view']);

        $response = $this->get(route('shipments.invoice.pdf', $shipment));

        $response->assertOk();
        $this->assertStringContainsString('pdf', (string) $response->headers->get('content-type'));
    }
}
