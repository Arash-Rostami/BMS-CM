<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Operational\PurchaseRequestResource\Pages\CreatePurchaseRequest;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ScratchUnitRequiredTest extends TestCase
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
        $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_scratch(): void
    {
        $dept = Department::factory()->create();
        $user = $this->actingAsUserWithPermissions(['purchase_request.create', 'purchase_request.view']);
        $user->forceFill(['department_id' => $dept->id])->save();

        $status = Status::factory()->create([
            'type' => PurchaseRequest::TYPE_PURCHASE_REQUEST,
            'english_type' => PurchaseRequest::TYPE_PURCHASE_REQUEST,
            'name' => 'Under Review',
            'english_name' => 'Under Review',
        ]);
        $itemStatus = Status::factory()->create([
            'type' => PurchaseRequestItem::TYPE_PURCHASE_REQUEST,
            'english_type' => PurchaseRequestItem::TYPE_PURCHASE_REQUEST,
            'name' => 'Under Review',
            'english_name' => 'Under Review',
        ]);
        $costCenter = Department::factory()->create();
        $product = Product::factory()->create();

        $result = Livewire::test(CreatePurchaseRequest::class)
            ->fillForm([
                'status_id' => $status->id,
                'cost_center_id' => $costCenter->id,
                'required_by_date' => now()->addDays(10)->format('Y-m-d'),
                'urgency_level' => 'medium',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'status_id' => $itemStatus->id,
                        'quantity' => 5,
                        'unit' => null,
                        'estimated_cost' => 20,
                    ],
                ],
            ])
            ->call('create');

        dump($result->instance()->form->getState());
        $result->assertHasFormErrors();
    }
}
